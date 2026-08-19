<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicUserPivot;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Fase C1.1 — extraído de AppointmentController, sem nenhuma mudança de
 * comportamento/regra visível. Concentra toda a matemática de agenda de um
 * profissional (disponibilidade, conflito, gaps, próximo horário livre); o
 * controller continua responsável por HTTP/validação/persistência.
 *
 * Fase C1.1.1 — as consultas a Appointment aqui filtram clinic_id
 * EXPLICITAMENTE, além do global scope ClinicScope (que continua ativo e
 * inalterado). Isso é defesa em profundidade, não desconfiança do
 * ClinicScope: ele é aplicado corretamente em toda requisição HTTP real
 * (ClinicScope::apply() só vira no-op quando app()->runningInConsole() é
 * true, o que nunca acontece num request HTTP de produção — só em
 * comandos/jobs de console, e nenhum toca esta classe hoje). O motivo de
 * blindar aqui mesmo assim: este service foi extraído do controller
 * justamente para ser reutilizável — é exatamente esse tipo de classe que
 * tende a ganhar um consumidor futuro fora do contexto HTTP (um job de
 * lembrete, um comando de auditoria), onde ClinicScope não ajudaria em
 * nada. O filtro explícito garante isolamento de tenant mesmo nesse cenário
 * futuro, sem depender de quem chama lembrar de estar num request HTTP.
 */
class AppointmentSchedulingService
{
    /**
     * Teto absoluto de horário quando NEM o profissional NEM a clínica têm
     * qualquer configuração de horário — mesmo intervalo que já era usado
     * só para sugestão de horários livres (ver dayAvailability() abaixo) e
     * que já é o limite visual da própria grade da Agenda
     * (GRID_FLOOR_HOUR/GRID_CEIL_HOUR em useEffectiveSchedule.js). Um
     * agendamento nunca pode existir fora de "algum" horário — "sem
     * configuração" deixou de significar "sem restrição nenhuma".
     */
    private const DEFAULT_HOURS = ['start' => '07:00', 'end' => '21:00'];

    /**
     * Bloqueia criar/editar um agendamento que caia num feriado nacional
     * (quando a clínica ativou essa regra), num dia que o profissional não
     * atende, ou fora do horário de atendimento efetivo (profissional ∩
     * clínica, com fallback pra DEFAULT_HOURS quando nenhum dos dois tem
     * horário configurado — nunca "sem restrição"). Feriado é checado
     * PRIMEIRO de propósito — tem precedência sobre dia/horário configurado
     * (ver regra aprovada: feriado sempre vence dia de atendimento normal).
     */
    public function assertProfessionalAvailable(int $professionalId, int $clinicId, Carbon $start, Carbon $end, ?int $chairId = null, ?int $excludeAppointmentId = null): void
    {
        $clinic = Clinic::find($clinicId);
        if ($clinic?->considersNationalHolidays() && BrazilianHolidayService::isHoliday($start)) {
            throw ValidationException::withMessages([
                'start' => 'Este dia está configurado como feriado e não possui atendimento.',
            ]);
        }

        $professional = User::find($professionalId);
        $pivot = $professional?->clinicPivotFor($clinicId);

        // effectiveWorksOnDate() já é determinístico (sem configuração =
        // atende todos os dias, ver ClinicUserPivot::workingDaysResolved) —
        // só precisa de pivot pra existir a checagem em si.
        if ($pivot && ! $pivot->effectiveWorksOnDate($clinic, $start)) {
            throw ValidationException::withMessages([
                'start' => 'Este profissional não possui atendimento neste dia.',
            ]);
        }

        // Sem pivot (profissional nem é membro desta clínica), não há
        // checagem de dia/horário pra fazer — igual já era antes (ver
        // effectiveWorksOnDate() acima, mesmo critério). Com pivot,
        // effectiveWorkingHours() pode retornar null (nem profissional nem
        // clínica configuraram horário pra este dia) — antes disso
        // significava "sem restrição"; agora cai no teto absoluto de
        // DEFAULT_HOURS, igual ao que dayAvailability() já fazia pra
        // sugestão de horários.
        if ($pivot) {
            $hours = $pivot->effectiveWorkingHours($clinic, ClinicUserPivot::dayKeyFor($start)) ?? self::DEFAULT_HOURS;

            if ($start->format('H:i') < $hours['start'] || $end->format('H:i') > $hours['end']) {
                throw ValidationException::withMessages([
                    'start' => "Este horário está fora do horário de atendimento ({$hours['start']}–{$hours['end']}).",
                ]);
            }
        }

        $this->assertNoConflict($clinicId, $professionalId, $chairId, $start, $end, $excludeAppointmentId);
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
     * cancelar/faltar libera o horário sem apagar o registro). clinic_id
     * explícito (C1.1.1) — ver docblock da classe.
     */
    private function assertNoConflict(int $clinicId, int $professionalId, ?int $chairId, Carbon $start, Carbon $end, ?int $excludeAppointmentId = null): void
    {
        $conflict = Appointment::where('clinic_id', $clinicId)
            ->where(function ($q) use ($professionalId, $chairId) {
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
     * Disponibilidade completa de UM dia: horários cheios (respeitando a
     * duração pedida, mesma lógica de sempre) + janelas parciais (menores
     * que a duração, mas ainda úteis — só calculadas quando não há nenhum
     * horário cheio, pra não poluir a lista à toa quando o cheio já
     * resolve). Reaproveitado tanto pelo dia exibido quanto pela busca do
     * próximo dia disponível (ver nextAvailableSlot). clinic_id explícito
     * (C1.1.1) — ver docblock da classe.
     */
    public function dayAvailability(int $clinicId, ?Clinic $clinic, User $professional, ?ClinicUserPivot $pivot, Carbon $date, int $duration, ?int $chairId): array
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
        $hours = $pivot?->effectiveWorkingHours($clinic, ClinicUserPivot::dayKeyFor($date)) ?? self::DEFAULT_HOURS;
        [$startH, $startM] = array_map('intval', explode(':', $hours['start']));
        [$endH, $endM] = array_map('intval', explode(':', $hours['end']));
        $windowStart = $date->copy()->setTime($startH, $startM);
        $windowEnd = $date->copy()->setTime($endH, $endM);

        // Mesma regra de conflito de assertNoConflict: profissional OU
        // cadeira ocupados bloqueiam, cancelado/faltou nunca ocupam.
        // clinic_id explícito (C1.1.1) — ver docblock da classe.
        $existing = Appointment::where('clinic_id', $clinicId)
            ->where(function ($q) use ($professional, $chairId) {
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
    public function nextAvailableSlot(int $clinicId, ?Clinic $clinic, User $professional, ?ClinicUserPivot $pivot, Carbon $fromDate, int $duration, ?int $chairId): ?array
    {
        for ($i = 1; $i <= 14; $i++) {
            $day = $fromDate->copy()->addDays($i);
            $result = $this->dayAvailability($clinicId, $clinic, $professional, $pivot, $day, $duration, $chairId);
            if (! empty($result['slots'])) {
                return ['date' => $day->format('Y-m-d'), 'time' => $result['slots'][0]];
            }
        }

        return null;
    }
}
