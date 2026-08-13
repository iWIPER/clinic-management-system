<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\ClinicUserPivot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AgendaSettingsController extends Controller
{
    public function index()
    {
        $clinicId = session('current_clinic_id');
        $viewer = auth()->user();
        $viewerCanManageOthers = in_array($viewer->roleInCurrentClinic(), ['owner', 'admin']);

        $professionals = User::clinicalProfessionalsOf($clinicId)
            ->orderBy('id')
            ->with(['clinics' => fn ($q) => $q->where('clinics.id', $clinicId)])
            ->get(['id', 'name'])
            ->map(function (User $u) use ($viewer, $clinicId, $viewerCanManageOthers) {
                $pivot = $u->clinics->first()?->pivot;
                $isSelf = $u->id === $viewer->id;

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'is_current_user' => $isSelf,
                    'can_edit' => $isSelf || $viewerCanManageOthers,
                    'agenda_visible_to_team' => (bool) ($pivot?->agenda_visible_to_team ?? true),
                    'working_days' => $pivot?->workingDaysResolved() ?? ClinicUserPivot::DEFAULT_WORKING_DAYS,
                    'working_hours' => $pivot?->workingHoursResolved() ?? ClinicUserPivot::DEFAULT_WORKING_HOURS,
                ];
            })
            ->sortBy(fn ($p) => $p['is_current_user'] ? 0 : 1)
            ->values();

        $clinic = Clinic::find($clinicId);

        return Inertia::render('ClinicSettings/Agendas', [
            'professionals' => $professionals,
            'dayKeys' => ClinicUserPivot::DAY_KEYS,
            'considerNationalHolidays' => $clinic->considersNationalHolidays(),
            'businessHours' => $clinic->businessHoursResolved(),
            'businessHoursEnforced' => $clinic->businessHoursEnforced(),
            // Regras da clínica (feriados + horário de funcionamento) —
            // não são configuração do profissional individual, só quem
            // administra a clínica pode mexer.
            'canManageClinicRules' => $viewerCanManageOthers,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $clinicId = session('current_clinic_id');
        $viewer = auth()->user();

        // Tenant safety: o profissional-alvo precisa pertencer à clínica
        // atual, senão nem existe pivot pra atualizar.
        abort_unless($user->clinics()->where('clinics.id', $clinicId)->exists(), 404);

        // Cada profissional mexe na própria configuração; owner/admin também
        // podem gerenciar a dos demais — mesma regra já usada em
        // TeamController::authorizeAdmin(), não uma lógica de permissão nova.
        $canManageOthers = in_array($viewer->roleInCurrentClinic(), ['owner', 'admin']);
        abort_unless($user->id === $viewer->id || $canManageOthers, 403);

        $rules = [
            'agenda_visible_to_team' => 'required|boolean',
            'working_start' => 'required|date_format:H:i',
            'working_end' => 'required|date_format:H:i|after:working_start',
        ];
        foreach (ClinicUserPivot::DAY_KEYS as $day) {
            $rules["working_days.{$day}"] = 'required|boolean';
        }
        $rules['working_days'] = 'required|array:' . implode(',', ClinicUserPivot::DAY_KEYS);

        $validated = $request->validate($rules);

        $this->assertWithinClinicRules($clinicId, $user, $validated);

        $user->clinics()->updateExistingPivot($clinicId, [
            'agenda_visible_to_team' => $validated['agenda_visible_to_team'],
            'working_days' => $validated['working_days'],
            'working_start' => $validated['working_start'],
            'working_end' => $validated['working_end'],
        ]);

        // Consumido via axios (Agendas.vue salva por toggle, sem reload de
        // página) — JSON, não redirect, mesmo padrão do ChairController.
        return response()->json([
            'agenda_visible_to_team' => $validated['agenda_visible_to_team'],
            'working_days' => $validated['working_days'],
            'working_hours' => ['start' => $validated['working_start'], 'end' => $validated['working_end']],
        ]);
    }

    /**
     * Rejeita a MUDANÇA que o profissional está tentando salvar agora se
     * ela ultrapassar uma regra obrigatória da clínica (dia fechado, ou
     * horário fora da janela permitida naquele dia). Só valida o que
     * realmente mudou em relação ao que já estava salvo — um profissional
     * que já tinha sábado ligado antes de a clínica bloquear sábado
     * continua podendo salvar outros campos (ex.: agenda_visible_to_team)
     * sem ser barrado por um dado antigo que ele nem tocou; o dado
     * permanece intacto em clinic_user até ele mesmo tentar mudá-lo de
     * verdade. Isso preserva "não apagar/sobrescrever configuração
     * individual" mesmo com a validação ativa.
     */
    private function assertWithinClinicRules(int $clinicId, User $user, array $validated): void
    {
        $clinic = Clinic::find($clinicId);
        if (! $clinic?->businessHoursEnforced()) {
            return;
        }

        $current = $user->clinicPivotFor($clinicId);
        $currentDays = $current?->workingDaysResolved() ?? ClinicUserPivot::DEFAULT_WORKING_DAYS;
        $hoursChanged = ! $current
            || $current->working_start !== $validated['working_start']
            || $current->working_end !== $validated['working_end'];

        foreach (ClinicUserPivot::DAY_KEYS as $day) {
            if (! $validated['working_days'][$day]) {
                continue;
            }

            $dayChanged = ! ($currentDays[$day] ?? false);
            if (! $dayChanged && ! $hoursChanged) {
                continue;
            }

            $clinicDay = $clinic->businessHoursFor($day);
            if (! $clinicDay) {
                continue;
            }

            if (! $clinicDay['enabled']) {
                throw ValidationException::withMessages([
                    'working_days' => 'A clínica não atende neste dia — regra obrigatória da clínica.',
                ]);
            }

            if ($validated['working_start'] < $clinicDay['start'] || $validated['working_end'] > $clinicDay['end']) {
                throw ValidationException::withMessages([
                    'working_end' => "O horário precisa estar dentro de {$clinicDay['start']}–{$clinicDay['end']}, definido como regra obrigatória da clínica.",
                ]);
            }
        }
    }

    /**
     * Feriado nacional é configuração GERAL da clínica — endpoint próprio
     * (não o mesmo de update() acima, que é por profissional), só
     * owner/admin pode mexer.
     */
    public function updateHolidaySettings(Request $request)
    {
        $clinicId = session('current_clinic_id');
        $viewer = auth()->user();

        abort_unless(in_array($viewer->roleInCurrentClinic(), ['owner', 'admin']), 403);

        $validated = $request->validate([
            'consider_national_holidays' => 'required|boolean',
        ]);

        $clinic = Clinic::findOrFail($clinicId);
        // Merge, não overwrite — "settings" é um bag genérico, outras
        // chaves podem existir aqui no futuro e não devem ser apagadas.
        $clinic->update([
            'settings' => array_merge($clinic->settings ?? [], [
                'consider_national_holidays' => $validated['consider_national_holidays'],
            ]),
        ]);

        return response()->json([
            'consider_national_holidays' => $validated['consider_national_holidays'],
        ]);
    }

    /**
     * Horário de funcionamento por dia da semana + se a regra é obrigatória
     * — regra GERAL da clínica (mesmo espírito de updateHolidaySettings
     * acima), só owner/admin pode mexer. Nunca escreve em clinic_user —
     * só em clinics.business_hours/business_hours_enforced.
     */
    public function updateBusinessHours(Request $request)
    {
        $clinicId = session('current_clinic_id');
        $viewer = auth()->user();

        abort_unless(in_array($viewer->roleInCurrentClinic(), ['owner', 'admin']), 403);

        $rules = ['enforced' => 'required|boolean'];
        foreach (ClinicUserPivot::DAY_KEYS as $day) {
            $rules["days.{$day}.enabled"] = 'required|boolean';
            $rules["days.{$day}.start"] = "nullable|required_if:days.{$day}.enabled,true|date_format:H:i";
            $rules["days.{$day}.end"] = "nullable|required_if:days.{$day}.enabled,true|date_format:H:i|after:days.{$day}.start";
        }
        $rules['days'] = 'required|array:' . implode(',', ClinicUserPivot::DAY_KEYS);

        $validated = $request->validate($rules);

        $businessHours = [];
        foreach (ClinicUserPivot::DAY_KEYS as $day) {
            $d = $validated['days'][$day];
            $businessHours[$day] = [
                'enabled' => $d['enabled'],
                'start' => $d['enabled'] ? $d['start'] : null,
                'end' => $d['enabled'] ? $d['end'] : null,
            ];
        }

        $clinic = Clinic::findOrFail($clinicId);
        $clinic->update([
            'business_hours' => $businessHours,
            'business_hours_enforced' => $validated['enforced'],
        ]);

        return response()->json([
            'business_hours' => $clinic->businessHoursResolved(),
            'business_hours_enforced' => $clinic->businessHoursEnforced(),
        ]);
    }
}
