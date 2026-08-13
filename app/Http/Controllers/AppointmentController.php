<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentReturn;
use App\Models\Chair;
use App\Models\Clinic;
use App\Models\ClinicUserPivot;
use App\Models\Patient;
use App\Models\PatientTag;
use App\Models\Treatment;
use App\Models\User;
use App\Services\BrazilianHolidayService;
use App\Services\PatientMarkerService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AppointmentController extends Controller
{
    public function __construct(private PatientMarkerService $markerService) {}

    public function index(Request $request)
    {
        $clinicId = session('current_clinic_id');
        $viewer = $request->user();

        $weekStart = $request->input('week')
            ? Carbon::parse($request->input('week'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $chairs = Chair::where('clinic_id', $clinicId)->orderBy('id')->get(['id', 'name', 'color']);
        $resolvedChairId = $this->resolveChairFilter($request, $chairs);

        ['visible' => $professionals, 'hiddenIds' => $hiddenProfessionalIds] =
            $this->agendaProfessionalsPayload($clinicId, $viewer);

        $resolvedProfessionalId = $this->resolveProfessionalFilter($request, $viewer, $clinicId);

        $appointments = Appointment::query()
            ->with([
                'patient:id,nome,sobrenome,telefone,email',
                'professional:id,name',
                'treatment:id,nome,duracao_padrao',
                'chair:id,name,color',
                'consultation:id,appointment_id,status',
                'tags:id,name,color',
                'appointmentReturn:id,appointment_id,due_date,reason,status',
            ])
            ->whereBetween('start', [$weekStart->startOfDay(), $weekEnd])
            ->when($resolvedProfessionalId, fn ($q, $id) => $q->where('professional_id', $id))
            ->when($resolvedChairId, fn ($q, $id) => $q->where('chair_id', $id))
            // Aplica sempre, inclusive em "Todos" — agenda não disponibilizada
            // pro time nunca aparece pra ninguém além do próprio dono dela.
            ->whereNotIn('professional_id', $hiddenProfessionalIds)
            ->orderBy('start')
            ->get();

        $clinic = Clinic::find($clinicId);

        return Inertia::render('Appointments/Index', [
            'appointments' => $appointments,
            'professionals' => $professionals,
            'chairs' => $chairs,
            'maxChairs' => Chair::MAX_PER_CLINIC,
            'weekStart' => $weekStart->format('Y-m-d'),
            'filters' => [
                'professional_id' => $resolvedProfessionalId ? (string) $resolvedProfessionalId : 'all',
                'chair_id' => $this->chairFilterForFrontend($request, $resolvedChairId),
            ],
            'considerNationalHolidays' => $clinic->considersNationalHolidays(),
            'holidays' => BrazilianHolidayService::forRange($weekStart, $weekEnd),
            'availableMarkers' => $this->markerService->availableMarkers($clinicId),
            'markerLimit' => PatientMarkerService::MAX_MARKERS_PER_PATIENT,
            ...$this->clinicScheduleRulesPayload($clinic),
        ]);
    }

    /**
     * Regra administrativa de horário da clínica (ver Configurações →
     * Agendas → Regras da clínica) — a Agenda usa isto só pra DECISÃO
     * VISUAL (grade dinâmica, bandas de bloqueio, badges); o bloqueio real
     * de criação continua sendo assertProfessionalAvailable(), que já
     * aplica a mesma regra via ClinicUserPivot::effective*.
     */
    private function clinicScheduleRulesPayload(?Clinic $clinic): array
    {
        return [
            'businessHours' => ($clinic ?? new Clinic())->businessHoursResolved(),
            'businessHoursEnforced' => (bool) $clinic?->businessHoursEnforced(),
        ];
    }

    /**
     * Monta a lista de "Agendas" pra sidebar (só quem o usuário logado pode
     * ver — ele mesmo sempre, os demais só se disponibilizaram a própria
     * agenda pro time) e a lista de ids a esconder das consultas retornadas
     * (usada tanto no filtro específico quanto em "Todos"). Única fonte
     * dessa regra — ver User::canViewAgendaOf().
     */
    private function agendaProfessionalsPayload(int $clinicId, User $viewer): array
    {
        $all = User::clinicalProfessionalsOf($clinicId)
            ->orderBy('id')
            ->with(['clinics' => fn ($q) => $q->where('clinics.id', $clinicId)])
            ->get(['id', 'name']);

        $hiddenIds = collect();
        $visible = collect();

        foreach ($all as $professional) {
            $pivot = $professional->clinics->first()?->pivot;
            $isSelf = $professional->id === $viewer->id;
            $isVisible = $isSelf || (bool) $pivot?->agenda_visible_to_team;

            if (! $isVisible) {
                $hiddenIds->push($professional->id);
                continue;
            }

            $visible->push([
                'id' => $professional->id,
                'name' => $professional->name,
                'is_current_user' => $isSelf,
                'working_days' => $pivot?->workingDaysResolved() ?? ClinicUserPivot::DEFAULT_WORKING_DAYS,
                // null = sem restrição de horário configurada — não usar
                // workingHoursResolved() aqui, esse é só pro formulário de
                // configurações (ver ClinicUserPivot::workingHoursConfigured).
                'working_hours' => $pivot?->workingHoursConfigured(),
            ]);
        }

        // Usuário logado primeiro; demais preservam a ordem de id (sortBy é
        // estável — PHP 8 garante isso pra uasort/usort).
        $visible = $visible->sortBy(fn ($p) => $p['is_current_user'] ? 0 : 1)->values();

        return ['visible' => $visible, 'hiddenIds' => $hiddenIds];
    }

    /**
     * Resolve o filtro de profissional efetivo pra query:
     *  - ausente ou 'all' -> sem filtro (Todos, comportamento de sempre);
     *  - id numérico -> filtra por ele, mas só se o usuário logado pode ver
     *    essa agenda (ver User::canViewAgendaOf) — nunca confia só na UI.
     */
    private function resolveProfessionalFilter(Request $request, User $viewer, int $clinicId): ?int
    {
        $raw = $request->input('professional_id');

        if ($raw === null || $raw === 'all') {
            return null;
        }

        $targetId = (int) $raw;

        abort_unless($viewer->canViewAgendaOf($targetId, $clinicId), 403, 'Você não tem acesso a essa agenda.');

        return $targetId;
    }

    /**
     * Lista de profissionais pro formulário de agendamento — mesma
     * população de sempre (todo membro da clínica, sem filtrar por cargo,
     * já que qualquer um pode ser designado como quem atende), só
     * enriquecida com working_days/working_hours pra UI avisar quando a
     * data/hora escolhida está fora do expediente (o backend continua
     * sendo quem garante isso de verdade — ver assertProfessionalAvailable).
     */
    private function professionalsWithWorkingDays(int $clinicId)
    {
        return User::whereHas('clinics', fn ($q) => $q->where('clinics.id', $clinicId))
            ->with(['clinics' => fn ($q) => $q->where('clinics.id', $clinicId)])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'working_days' => $u->clinics->first()?->pivot?->workingDaysResolved() ?? ClinicUserPivot::DEFAULT_WORKING_DAYS,
                // null = sem restrição configurada, ver comentário análogo
                // em agendaProfessionalsPayload().
                'working_hours' => $u->clinics->first()?->pivot?->workingHoursConfigured(),
            ]);
    }

    /**
     * Bloqueia criar/editar um agendamento que caia num feriado nacional
     * (quando a clínica ativou essa regra), num dia que o profissional não
     * atende, ou fora do horário de atendimento dele. Feriado é checado
     * PRIMEIRO de propósito — tem precedência sobre dia/horário configurado
     * (ver regra aprovada: feriado sempre vence dia de atendimento normal).
     * Quem não tem configuração de agenda (pivot nulo, ex.: staff
     * não-clínico) só é bloqueado pelo feriado — dia/horário sempre foram
     * "sem restrição" pra esse caso, e continuam sendo.
     */
    private function assertProfessionalAvailable(int $professionalId, int $clinicId, Carbon $start, Carbon $end, ?int $chairId = null, ?int $excludeAppointmentId = null): void
    {
        $clinic = Clinic::find($clinicId);
        if ($clinic?->considersNationalHolidays() && BrazilianHolidayService::isHoliday($start)) {
            throw ValidationException::withMessages([
                'start' => 'Este dia está configurado como feriado e não possui atendimento.',
            ]);
        }

        $professional = User::find($professionalId);
        $pivot = $professional?->clinicPivotFor($clinicId);

        if ($pivot) {
            // effective* aplica a regra administrativa da clínica por cima
            // (quando obrigatória) — ver ClinicUserPivot::effectiveWorksOnDate/
            // effectiveIsWithinWorkingHours. Sem regra ativa, comportamento
            // idêntico a worksOnDate()/isWithinWorkingHours() de sempre.
            if (! $pivot->effectiveWorksOnDate($clinic, $start)) {
                throw ValidationException::withMessages([
                    'start' => 'Este profissional não possui atendimento neste dia.',
                ]);
            }

            if (! $pivot->effectiveIsWithinWorkingHours($clinic, $start, $end)) {
                throw ValidationException::withMessages([
                    'start' => 'Este horário está fora do horário de atendimento deste profissional.',
                ]);
            }
        }

        $this->assertNoConflict($professionalId, $chairId, $start, $end, $excludeAppointmentId);
    }

    /**
     * Fonte de verdade de "esse horário está livre?" pra store()/update() —
     * o botão "Encontrar horário" só sugere, quem garante de verdade no
     * momento de salvar é isto aqui (outro usuário pode ter agendado depois
     * da sugestão ser consultada). Conflito é OR, não AND: bloqueia se o
     * profissional já está ocupado em QUALQUER cadeira nesse intervalo, OU
     * se a cadeira já está ocupada por QUALQUER profissional — as duas
     * coisas são fisicamente impossíveis ao mesmo tempo, mesmo combinadas.
     * cancelled/no_show nunca contam como ocupação (ver regra aprovada:
     * cancelar/faltar libera o horário sem apagar o registro).
     */
    private function assertNoConflict(int $professionalId, ?int $chairId, Carbon $start, Carbon $end, ?int $excludeAppointmentId = null): void
    {
        $conflict = Appointment::where(function ($q) use ($professionalId, $chairId) {
            $q->where('professional_id', $professionalId);
            if ($chairId) {
                $q->orWhere('chair_id', $chairId);
            }
        })
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->when($excludeAppointmentId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->where('start', '<', $end)
            ->where('end', '>', $start)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'start' => 'Este horário já está ocupado. Escolha outro horário.',
            ]);
        }
    }

    /**
     * "Encontrar horário" do modal de agendamento — sugere horários vagos
     * de verdade, reaproveitando as MESMAS regras de assertProfessionalAvailable
     * (feriado, dia de atendimento, horário de atendimento) mais checagem de
     * conflito contra agendamentos já existentes desse profissional (e da
     * cadeira, se informada) naquele dia — a única coisa que
     * assertProfessionalAvailable não verifica, porque ela nunca precisou:
     * é só um gate de sim/não pra UM horário já escolhido, não um buscador.
     * Nenhuma tabela/coluna nova — só leitura sobre o que já existe.
     */
    public function availableSlots(Request $request)
    {
        $clinicId = session('current_clinic_id');

        $validated = $request->validate([
            'professional_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'chair_id' => ['nullable', Rule::exists('chairs', 'id')->where('clinic_id', $clinicId)],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();
        $duration = (int) $validated['duration_minutes'];
        $chairId = $validated['chair_id'] ?? null;

        $clinic = Clinic::find($clinicId);
        $professional = User::findOrFail($validated['professional_id']);
        $pivot = $professional->clinicPivotFor($clinicId);

        $day = $this->dayAvailability($clinic, $professional, $pivot, $date, $duration, $chairId);

        // "Próximo horário disponível" só faz sentido calcular quando o dia
        // pedido não tem nenhum horário cheio — é o item 4 da prioridade do
        // pedido (feito por último, só quando os anteriores não resolvem).
        $nextAvailable = empty($day['slots'])
            ? $this->nextAvailableSlot($clinic, $professional, $pivot, $date, $duration, $chairId)
            : null;

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'slots' => $day['slots'],
            'partial_slots' => $day['partial_slots'],
            'message' => $day['message'],
            'next_available' => $nextAvailable,
        ]);
    }

    /**
     * Disponibilidade completa de UM dia: horários cheios (respeitando a
     * duração pedida, mesma lógica de sempre) + janelas parciais (menores
     * que a duração, mas ainda úteis — só calculadas quando não há nenhum
     * horário cheio, pra não poluir a lista à toa quando o cheio já
     * resolve). Reaproveitado tanto pelo dia exibido quanto pela busca do
     * próximo dia disponível (ver nextAvailableSlot).
     */
    private function dayAvailability(?Clinic $clinic, User $professional, ?ClinicUserPivot $pivot, Carbon $date, int $duration, ?int $chairId): array
    {
        if ($clinic?->considersNationalHolidays() && BrazilianHolidayService::isHoliday($date)) {
            return ['slots' => [], 'partial_slots' => [], 'message' => 'Este dia está configurado como feriado e não possui atendimento.'];
        }

        if ($pivot && ! $pivot->effectiveWorksOnDate($clinic, $date)) {
            return ['slots' => [], 'partial_slots' => [], 'message' => 'Este profissional não possui atendimento neste dia.'];
        }

        // effectiveWorkingHours já aplica a regra administrativa da clínica
        // por cima quando obrigatória (ver ClinicUserPivot). Sem pivot
        // (staff sem agenda própria), mantém o mesmo intervalo padrão da
        // grade de sempre — não faz sentido sugerir um horário que a
        // própria grade nunca mostraria.
        $hours = $pivot?->effectiveWorkingHours($clinic, ClinicUserPivot::dayKeyFor($date)) ?? ['start' => '07:00', 'end' => '21:00'];
        [$startH, $startM] = array_map('intval', explode(':', $hours['start']));
        [$endH, $endM] = array_map('intval', explode(':', $hours['end']));
        $windowStart = $date->copy()->setTime($startH, $startM);
        $windowEnd = $date->copy()->setTime($endH, $endM);

        // Mesma regra de conflito de assertNoConflict: profissional OU
        // cadeira ocupados bloqueiam, cancelado/faltou nunca ocupam.
        $existing = Appointment::where(function ($q) use ($professional, $chairId) {
                $q->where('professional_id', $professional->id);
                if ($chairId) {
                    $q->orWhere('chair_id', $chairId);
                }
            })
            ->whereDate('start', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->get(['start', 'end']);

        $step = 15; // minutos — mesma granularidade dos slots clicáveis na grade.
        $now = Carbon::now();
        $slots = [];
        $cursor = $windowStart->copy();

        while (count($slots) < 8 && $cursor->copy()->addMinutes($duration)->lte($windowEnd)) {
            $slotEnd = $cursor->copy()->addMinutes($duration);

            $isPast = $date->isSameDay($now) && $cursor->lt($now);
            $overlaps = ! $isPast && $existing->contains(
                fn ($appt) => $cursor->lt(Carbon::parse($appt->end)) && $slotEnd->gt(Carbon::parse($appt->start))
            );

            if (! $isPast && ! $overlaps) {
                $slots[] = $cursor->format('H:i');
            }

            $cursor->addMinutes($step);
        }

        $partialSlots = empty($slots)
            ? $this->partialGaps($windowStart, $windowEnd, $existing, $duration, $date, $now)
            : [];

        return ['slots' => $slots, 'partial_slots' => $partialSlots, 'message' => null];
    }

    /**
     * Janelas livres menores que a duração pedida (ex.: 15min livres pra uma
     * consulta de 30min) — mostradas separadas dos horários cheios, nunca
     * como se comportassem a duração inteira (ver pedido: "não deve ser
     * tratada como um horário válido"). Teto de 5 sugestões.
     */
    private function partialGaps(Carbon $windowStart, Carbon $windowEnd, $existing, int $duration, Carbon $date, Carbon $now): array
    {
        $cursor = $windowStart->copy();
        if ($date->isSameDay($now) && $cursor->lt($now)) {
            $minutesIn = max(0, $windowStart->diffInMinutes($now));
            $cursor = $windowStart->copy()->addMinutes((int) (ceil($minutesIn / 15) * 15));
        }

        $busy = $existing
            ->map(fn ($a) => [Carbon::parse($a->start), Carbon::parse($a->end)])
            ->sortBy(fn ($pair) => $pair[0]->timestamp)
            ->values();

        $gaps = [];
        foreach ($busy as [$busyStart, $busyEnd]) {
            if ($busyStart->gt($cursor)) {
                $gaps[] = [$cursor->copy(), $busyStart->copy()];
            }
            if ($busyEnd->gt($cursor)) {
                $cursor = $busyEnd->copy();
            }
        }
        if ($cursor->lt($windowEnd)) {
            $gaps[] = [$cursor->copy(), $windowEnd->copy()];
        }

        $partial = [];
        foreach ($gaps as [$gapStart, $gapEnd]) {
            $minutes = $gapStart->diffInMinutes($gapEnd);
            if ($minutes >= 15 && $minutes < $duration) {
                $partial[] = ['start' => $gapStart->format('H:i'), 'minutes' => $minutes];
            }
            if (count($partial) >= 5) {
                break;
            }
        }

        return $partial;
    }

    /**
     * Escaneia pra frente a partir do dia SEGUINTE ao pedido até achar o
     * primeiro horário cheio — teto de 14 dias, nunca procura
     * indefinidamente (pedido explícito: "defina uma janela razoável").
     */
    private function nextAvailableSlot(?Clinic $clinic, User $professional, ?ClinicUserPivot $pivot, Carbon $fromDate, int $duration, ?int $chairId): ?array
    {
        for ($i = 1; $i <= 14; $i++) {
            $day = $fromDate->copy()->addDays($i);
            $result = $this->dayAvailability($clinic, $professional, $pivot, $day, $duration, $chairId);
            if (! empty($result['slots'])) {
                return ['date' => $day->format('Y-m-d'), 'time' => $result['slots'][0]];
            }
        }

        return null;
    }

    /**
     * Resolve o filtro de cadeira efetivo pra query:
     *  - parâmetro ausente (primeiro acesso, sem escolha explícita) -> cadeira
     *    default (a mais antiga da clínica, "Cadeira 01");
     *  - 'all' (usuário escolheu "Todas" explicitamente) -> sem filtro;
     *  - id numérico -> filtra por ele.
     * Ver Index.vue (chairFilter) — o front sempre re-envia o valor
     * resolvido nas navegações seguintes, então só o load "cru" (sem
     * chair_id na URL) passa por aqui.
     */
    private function resolveChairFilter(Request $request, $chairs): ?int
    {
        $raw = $request->input('chair_id');

        if ($raw === null) {
            return optional($chairs->first())->id;
        }

        if ($raw === 'all') {
            return null;
        }

        return (int) $raw;
    }

    private function chairFilterForFrontend(Request $request, ?int $resolvedChairId): string
    {
        $raw = $request->input('chair_id');

        if ($raw !== null) {
            return (string) $raw;
        }

        return $resolvedChairId ? (string) $resolvedChairId : 'all';
    }

    /**
     * A tela cheia agora reaproveita exatamente a mesma resolução de
     * filtros/visibilidade de index() — ela ganhou a sidebar de Cadeiras e
     * Agendas nesta rodada, então precisa das mesmas regras já aprovadas
     * (senão viraria um atalho pra ver agendas não compartilhadas, ou o
     * sentinel 'all' quebraria o filtro de cadeira). Nenhuma regra nova,
     * só a mesma autoridade reutilizada.
     */
    public function fullscreen(Request $request)
    {
        $clinicId = session('current_clinic_id');
        $viewer = $request->user();

        $weekStart = $request->input('week')
            ? Carbon::parse($request->input('week'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $chairs = Chair::where('clinic_id', $clinicId)->orderBy('id')->get(['id', 'name', 'color']);
        $resolvedChairId = $this->resolveChairFilter($request, $chairs);

        ['visible' => $professionals, 'hiddenIds' => $hiddenProfessionalIds] =
            $this->agendaProfessionalsPayload($clinicId, $viewer);

        $resolvedProfessionalId = $this->resolveProfessionalFilter($request, $viewer, $clinicId);

        $appointments = Appointment::query()
            ->with([
                'patient:id,nome,sobrenome,telefone,email',
                'professional:id,name',
                'treatment:id,nome,duracao_padrao',
                'chair:id,name,color',
                'consultation:id,appointment_id,status,check_in_at',
                'tags:id,name,color',
                'appointmentReturn:id,appointment_id,due_date,reason,status',
            ])
            ->whereBetween('start', [$weekStart->startOfDay(), $weekEnd])
            ->when($resolvedProfessionalId, fn ($q, $id) => $q->where('professional_id', $id))
            ->when($resolvedChairId, fn ($q, $id) => $q->where('chair_id', $id))
            ->whereNotIn('professional_id', $hiddenProfessionalIds)
            ->orderBy('start')
            ->get();

        $clinic = Clinic::find($clinicId);

        return Inertia::render('Appointments/Fullscreen', [
            'appointments' => $appointments,
            'professionals' => $professionals,
            'chairs' => $chairs,
            'maxChairs' => Chair::MAX_PER_CLINIC,
            'weekStart' => $weekStart->format('Y-m-d'),
            'filters' => [
                'professional_id' => $resolvedProfessionalId ? (string) $resolvedProfessionalId : 'all',
                'chair_id' => $this->chairFilterForFrontend($request, $resolvedChairId),
            ],
            'considerNationalHolidays' => $clinic->considersNationalHolidays(),
            'holidays' => BrazilianHolidayService::forRange($weekStart, $weekEnd),
            'availableMarkers' => $this->markerService->availableMarkers($clinicId),
            'markerLimit' => PatientMarkerService::MAX_MARKERS_PER_PATIENT,
            ...$this->clinicScheduleRulesPayload($clinic),
        ]);
    }

    public function create(Request $request)
    {
        $clinicId = session('current_clinic_id');

        $patients = Patient::select('id', 'nome', 'sobrenome', 'telefone')
            ->orderBy('nome')
            ->get();

        $professionals = $this->professionalsWithWorkingDays($clinicId);

        $chairs = Chair::orderBy('name')->get(['id', 'name', 'color']);

        return Inertia::render('Appointments/Create', [
            'patients' => $patients,
            'professionals' => $professionals,
            'chairs' => $chairs,
            'defaultDate' => $request->input('date', now()->format('Y-m-d')),
            'defaultTime' => $request->input('time', '09:00'),
            'prefilledPatientId' => $request->input('patient_id'),
            'prefilledChairId' => $request->input('chair_id'),
            ...$this->scheduleContextForForm($clinicId),
        ]);
    }

    /**
     * Create/Edit não têm uma semana carregada (a data é escolhida livre no
     * formulário) — expõe o ano corrente + o seguinte, margem generosa pra
     * não precisar ida ao servidor toda vez que o usuário troca a data.
     * Inclui feriados + regras administrativas de horário (ver
     * clinicScheduleRulesPayload) — os formulários avulsos (Create/Edit)
     * precisam da MESMA informação que o modal da Agenda já usa, senão
     * conseguem submeter um horário que a regra da clínica bloqueia.
     */
    private function scheduleContextForForm(int $clinicId): array
    {
        $clinic = Clinic::find($clinicId);
        $now = Carbon::now();

        return [
            'considerNationalHolidays' => $clinic->considersNationalHolidays(),
            'holidays' => BrazilianHolidayService::forRange(
                $now->copy()->startOfYear(),
                $now->copy()->addYear()->endOfYear()
            ),
            ...$this->clinicScheduleRulesPayload($clinic),
        ];
    }

    public function store(Request $request)
    {
        $clinicId = session('current_clinic_id');

        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'professional_id' => 'required|exists:users,id',
            // Tratamento deixou de ser exigido pra reservar um horário —
            // Agendamento != Procedimento (o procedimento acontece depois,
            // no atendimento/prontuário). Continua aceito quando enviado
            // (Create.vue, o formulário avulso antigo, ainda manda sempre).
            'treatment_id' => ['nullable', Rule::exists('treatments', 'id')->where('clinic_id', $clinicId)],
            'chair_id' => ['nullable', Rule::exists('chairs', 'id')->where('clinic_id', $clinicId)],
            'start' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'notes' => 'nullable|string|max:200',
            'confirmation_requested' => 'nullable|boolean',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => PatientTag::markerExistsRule(),
            'return_option' => 'nullable|in:none,15d,1m,6m,12m,custom',
            'return_date' => 'nullable|date',
            'return_reason' => 'nullable|string|max:500',
        ]);

        $start = Carbon::parse($validated['start']);
        // duration_minutes manda quando enviado (novo modal); sem ele, cai
        // pro que o tratamento define (Create.vue, o formulário avulso
        // antigo, nunca manda duration_minutes — comportamento dele não
        // muda); sem os dois, 30min é o padrão.
        $treatment = isset($validated['treatment_id']) ? Treatment::find($validated['treatment_id']) : null;
        $duration = (int) ($validated['duration_minutes'] ?? $treatment?->duracao_padrao ?? 30);
        $end = $start->copy()->addMinutes($duration);

        $this->assertProfessionalAvailable($validated['professional_id'], $clinicId, $start, $end, $validated['chair_id'] ?? null);

        $patient = Patient::findOrFail($validated['patient_id']);
        $tagIds = $validated['tag_ids'] ?? [];
        if ($tagIds) {
            $this->markerService->assertAppointmentTagsWithinLimit($patient, $tagIds);
        }

        DB::transaction(function () use ($validated, $start, $end, $tagIds, $clinicId, $patient) {
            $appointment = Appointment::create([
                'patient_id' => $validated['patient_id'],
                'professional_id' => $validated['professional_id'],
                'treatment_id' => $validated['treatment_id'] ?? null,
                'chair_id' => $validated['chair_id'] ?? null,
                'start' => $start,
                'end' => $end,
                'status' => 'scheduled',
                'notes' => $validated['notes'] ?? null,
                'confirmation_requested' => $validated['confirmation_requested'] ?? false,
            ]);

            if ($tagIds) {
                $appointment->tags()->sync($tagIds);
            }

            $this->maybeCreateReturn($appointment, $validated, $clinicId, $patient);
        });

        // O modal de agendamento (aberto de dentro da própria Agenda) manda
        // esses 3 campos opcionais só pra não perder a semana/filtro que
        // estavam sendo vistos ao criar — sem isso, o redirect sempre volta
        // pra semana atual e "Todos", mesmo se o agendamento foi criado
        // numa semana futura ou com um filtro específico ativo. A tela
        // Create.vue (formulário avulso) nunca manda esses campos, então o
        // comportamento dela não muda em nada.
        return redirect()
            ->route('appointments.index', array_filter([
                'week' => $request->input('redirect_week'),
                'professional_id' => $request->input('redirect_professional_id'),
                'chair_id' => $request->input('redirect_chair_id'),
            ]))
            ->with('success', 'Agendamento criado com sucesso!');
    }

    /**
     * "Retornar em" — cria só uma PENDÊNCIA de retorno vinculada a este
     * agendamento, nunca um novo Appointment nem altera a consulta atual.
     * due_date é relativa à DATA DA CONSULTA (não a hoje) — "retornar em 1
     * mês" parte de quando o paciente foi atendido, não de quando o
     * agendamento foi criado. "Outro" manda return_date direto (pedido
     * explícito de data, não um número de dias).
     */
    private function maybeCreateReturn(Appointment $appointment, array $validated, int $clinicId, Patient $patient): void
    {
        $dueDate = $this->computeReturnDueDate($appointment, $validated);
        if (! $dueDate) {
            return;
        }

        AppointmentReturn::create([
            'clinic_id' => $clinicId,
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'professional_id' => $appointment->professional_id,
            'due_date' => $dueDate,
            'reason' => $validated['return_reason'] ?? null,
            'status' => 'pending',
        ]);
    }

    /**
     * Edição: reflete a escolha atual de "Retornar em" — cria se não
     * existia, atualiza data/motivo se já existia, remove se voltou pra
     * "Sem retorno". Nunca mexe no Appointment em si.
     */
    private function syncReturn(Appointment $appointment, array $validated, int $clinicId, Patient $patient): void
    {
        $dueDate = $this->computeReturnDueDate($appointment, $validated);

        if (! $dueDate) {
            $appointment->appointmentReturn?->delete();
            return;
        }

        AppointmentReturn::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'clinic_id' => $clinicId,
                'patient_id' => $patient->id,
                'professional_id' => $appointment->professional_id,
                'due_date' => $dueDate,
                'reason' => $validated['return_reason'] ?? null,
                'status' => 'pending',
            ]
        );
    }

    /**
     * due_date é relativa à DATA DA CONSULTA (não a hoje) — "retornar em 1
     * mês" parte de quando o paciente foi atendido, não de quando o
     * agendamento foi criado/editado. "Outro" manda return_date direto
     * (pedido explícito de data, não um número de dias).
     */
    private function computeReturnDueDate(Appointment $appointment, array $validated): ?Carbon
    {
        $option = $validated['return_option'] ?? 'none';
        if ($option === 'none') {
            return null;
        }

        return match ($option) {
            '15d' => $appointment->start->copy()->addDays(15),
            '1m' => $appointment->start->copy()->addMonthsNoOverflow(1),
            '6m' => $appointment->start->copy()->addMonthsNoOverflow(6),
            '12m' => $appointment->start->copy()->addMonthsNoOverflow(12),
            'custom' => isset($validated['return_date']) ? Carbon::parse($validated['return_date']) : null,
            default => null,
        };
    }

    public function edit(Appointment $appointment)
    {
        $clinicId = session('current_clinic_id');

        $patients = Patient::select('id', 'nome', 'sobrenome')->get();
        $professionals = $this->professionalsWithWorkingDays($clinicId);
        $chairs = Chair::orderBy('name')->get(['id', 'name', 'color']);

        return Inertia::render('Appointments/Edit', [
            'appointment' => $appointment->load(['patient', 'professional', 'chair', 'tags', 'appointmentReturn']),
            'patients' => $patients,
            'professionals' => $professionals,
            'chairs' => $chairs,
            'availableMarkers' => $this->markerService->availableMarkers($clinicId),
            'markerLimit' => PatientMarkerService::MAX_MARKERS_PER_PATIENT,
            ...$this->scheduleContextForForm($clinicId),
        ]);
    }

    public function update(Request $request, Appointment $appointment)
    {
        $clinicId = session('current_clinic_id');

        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'professional_id' => 'required|exists:users,id',
            // Tratamento não é mais definido na Agenda (ver store()) — aceito
            // só por compatibilidade caso algo externo ainda envie, nunca
            // exigido nem mostrado nos formulários.
            'treatment_id' => ['nullable', Rule::exists('treatments', 'id')->where('clinic_id', $clinicId)],
            'chair_id' => ['nullable', Rule::exists('chairs', 'id')->where('clinic_id', $clinicId)],
            'start' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'status' => 'required|in:scheduled,confirmed,in_attendance,cancelled,no_show,completed',
            'notes' => 'nullable|string|max:200',
            'confirmation_requested' => 'nullable|boolean',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => PatientTag::markerExistsRule(),
            'return_option' => 'nullable|in:none,15d,1m,6m,12m,custom',
            'return_date' => 'nullable|date',
            'return_reason' => 'nullable|string|max:500',
        ]);

        $treatment = isset($validated['treatment_id']) ? Treatment::find($validated['treatment_id']) : null;
        $start = Carbon::parse($validated['start']);
        $duration = (int) ($validated['duration_minutes'] ?? $treatment?->duracao_padrao ?? 30);
        $end = $start->copy()->addMinutes($duration);

        // Só revalida a regra de horário/dia quando o agendamento está
        // REALMENTE mudando de horário/profissional/cadeira — um
        // agendamento antigo que ficou fora do horário depois de uma
        // alteração administrativa continua editável pra tudo o que não é
        // reagendamento (status, observações etc.), sem ficar travado só
        // por reabrir a edição. "Mover" para outro horário inválido
        // continua bloqueado normalmente (ver assertProfessionalAvailable).
        $scheduleChanged = ! $appointment->start->equalTo($start)
            || ! $appointment->end->equalTo($end)
            || (int) $appointment->professional_id !== (int) $validated['professional_id']
            || (int) ($appointment->chair_id ?? 0) !== (int) ($validated['chair_id'] ?? 0);

        if ($scheduleChanged) {
            $this->assertProfessionalAvailable($validated['professional_id'], $clinicId, $start, $end, $validated['chair_id'] ?? null, $appointment->id);
        }

        $patient = Patient::findOrFail($validated['patient_id']);
        if ($request->has('tag_ids')) {
            $tagIds = $validated['tag_ids'] ?? [];
            if ($tagIds) {
                $this->markerService->assertAppointmentTagsWithinLimit($patient, $tagIds, $appointment->id);
            }
        }

        // Data/hora mudou = remarcação — usado no resumo de relacionamento do paciente.
        $wasRescheduled = ! $appointment->start->equalTo($start);

        $attributes = [
            'patient_id' => $validated['patient_id'],
            'professional_id' => $validated['professional_id'],
            'treatment_id' => $validated['treatment_id'] ?? null,
            'chair_id' => $validated['chair_id'] ?? null,
            'start' => $start,
            'end' => $end,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'reschedule_count' => $wasRescheduled ? $appointment->reschedule_count + 1 : $appointment->reschedule_count,
        ];
        if ($request->has('confirmation_requested')) {
            $attributes['confirmation_requested'] = $validated['confirmation_requested'];
        }

        DB::transaction(function () use ($appointment, $attributes, $request, $validated, $clinicId, $patient) {
            $appointment->update($attributes);
            if ($request->has('tag_ids')) {
                $appointment->tags()->sync($validated['tag_ids'] ?? []);
            }
            if ($request->has('return_option')) {
                $this->syncReturn($appointment, $validated, $clinicId, $patient);
            }
        });

        // Mesmo esquema de redirect_week/professional_id/chair_id de store()
        // — necessário agora que o modal de edição é aberto de dentro da
        // própria Agenda (ver AppointmentFormModal.vue): sem isso, salvar uma
        // edição sempre devolvia pra semana atual e "Todos", perdendo a
        // semana/filtro que o usuário estava vendo. Edit.vue (página avulsa,
        // ainda usada a partir do prontuário) nunca manda esses campos, então
        // continua caindo no comportamento de sempre (redirect sem filtros).
        return redirect()
            ->route('appointments.index', array_filter([
                'week' => $request->input('redirect_week'),
                'professional_id' => $request->input('redirect_professional_id'),
                'chair_id' => $request->input('redirect_chair_id'),
            ]))
            ->with('success', 'Agendamento atualizado!');
    }

    public function checkIn(Appointment $appointment)
    {
        $consultation = \App\Models\Consultation::firstOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'patient_id'      => $appointment->patient_id,
                'professional_id' => $appointment->professional_id,
                'status'          => 'aguardando',
                'check_in_at'     => now(),
            ]
        );

        if (! $consultation->check_in_at) {
            $consultation->update(['check_in_at' => now(), 'status' => 'aguardando']);
        }

        $appointment->update(['status' => 'in_attendance']);

        return back()->with('success', 'Check-in realizado! Paciente aguardando atendimento.');
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'status' => 'required|in:scheduled,confirmed,in_attendance,cancelled,no_show,completed',
        ]);

        $appointment->update(['status' => $validated['status']]);

        return back()->with('success', 'Status atualizado.');
    }
}
