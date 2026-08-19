<?php

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\User;
use App\Services\AppointmentSchedulingService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

// Fase C1.1 — testes unitários da lógica extraída de AppointmentController
// para AppointmentSchedulingService. Chamam o service diretamente (sem HTTP),
// reproduzindo os mesmos cenários já cobertos por AgendaAvailabilityTest.php
// e AppointmentAvailableSlotsTest.php via rota, mais os que não tinham
// nenhuma cobertura antes da extração (partialGaps, nextAvailableSlot
// esgotado, profissional sem pivot).

function setupSchedulingServiceContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-sched-' . uniqid(),
        'is_free' => true,
        'price_monthly_cents' => 0,
        'price_yearly_cents' => 0,
        'max_clinics' => 1,
        'max_patients' => 100,
        'max_users' => 5,
        'storage_gb' => 1,
        'features' => [],
    ]);

    $clinic = Clinic::create([
        'name' => 'Clínica Scheduling',
        'slug' => 'clinica-sched-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $professional = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($professional->id, ['role' => 'professional']);

    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Paciente', 'sobrenome' => 'Teste', 'status' => 'ativo']);

    return compact('clinic', 'professional', 'patient');
}

function nextSchedMonday(): Carbon
{
    return now()->next(Carbon::MONDAY)->startOfDay();
}

function service(): AppointmentSchedulingService
{
    return new AppointmentSchedulingService();
}

// ── assertProfessionalAvailable ──────────────────────────────────────────

test('an available professional with no conflicts passes silently', function () {
    ['clinic' => $clinic, 'professional' => $professional] = setupSchedulingServiceContext();
    $professional->clinics()->updateExistingPivot($clinic->id, ['working_start' => '09:00', 'working_end' => '18:00']);

    $monday = nextSchedMonday()->setTime(10, 0);

    service()->assertProfessionalAvailable($professional->id, $clinic->id, $monday, $monday->copy()->addMinutes(30));

    expect(true)->toBeTrue(); // não lançou exceção
});

test('a professional who does not work that day of the week is rejected', function () {
    ['clinic' => $clinic, 'professional' => $professional] = setupSchedulingServiceContext();
    $professional->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => ['mon' => false, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
    ]);

    $monday = nextSchedMonday()->setTime(10, 0);

    expect(fn () => service()->assertProfessionalAvailable($professional->id, $clinic->id, $monday, $monday->copy()->addMinutes(30)))
        ->toThrow(ValidationException::class);
});

test('a professional without any pivot configuration (not a member of this clinic) skips day/hours checks entirely', function () {
    ['clinic' => $clinic] = setupSchedulingServiceContext();
    $outsider = User::factory()->create(['email_verified_at' => now()]); // nunca vinculado a esta clínica

    $monday = nextSchedMonday()->setTime(23, 0); // horário absurdo — sem pivot, nada bloqueia por dia/hora

    service()->assertProfessionalAvailable($outsider->id, $clinic->id, $monday, $monday->copy()->addMinutes(30));

    expect(true)->toBeTrue();
});

// ── Feriados (precedência sobre dia/horário normal) ──────────────────────

test('a national holiday blocks even a professional configured to work that exact day, when the clinic has the setting on', function () {
    ['clinic' => $clinic, 'professional' => $professional] = setupSchedulingServiceContext();

    $nextYear = now()->year + 1;
    $christmas = Carbon::parse("{$nextYear}-12-25");
    $dayKey = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'][$christmas->dayOfWeekIso - 1];

    $professional->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true),
        'working_start' => '09:00', 'working_end' => '18:00',
    ]);
    $clinic->update(['settings' => ['consider_national_holidays' => true]]);

    expect($professional->clinicPivotFor($clinic->id)->working_days[$dayKey])->toBeTrue();

    expect(fn () => service()->assertProfessionalAvailable(
        $professional->id, $clinic->id, $christmas->copy()->setTime(10, 0), $christmas->copy()->setTime(10, 30)
    ))->toThrow(ValidationException::class, 'Este dia está configurado como feriado e não possui atendimento.');
});

test('the same holiday date is not blocked when the clinic setting is off', function () {
    ['clinic' => $clinic, 'professional' => $professional] = setupSchedulingServiceContext();

    $nextYear = now()->year + 1;
    $christmas = Carbon::parse("{$nextYear}-12-25");

    $professional->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true),
        'working_start' => '00:00', 'working_end' => '23:59',
    ]);

    service()->assertProfessionalAvailable(
        $professional->id, $clinic->id, $christmas->copy()->setTime(10, 0), $christmas->copy()->setTime(10, 30)
    );

    expect(true)->toBeTrue();
});

// ── Limites exatos de horário de atendimento ─────────────────────────────

test('booking exactly at the opening minute is allowed, exactly at the closing minute (as the end) is allowed, one minute past closing is rejected', function () {
    ['clinic' => $clinic, 'professional' => $professional] = setupSchedulingServiceContext();
    $professional->clinics()->updateExistingPivot($clinic->id, ['working_start' => '09:00', 'working_end' => '18:00']);
    $monday = nextSchedMonday();

    // Exatamente no início.
    service()->assertProfessionalAvailable($professional->id, $clinic->id, $monday->copy()->setTime(9, 0), $monday->copy()->setTime(9, 30));

    // Termina exatamente no fechamento.
    service()->assertProfessionalAvailable($professional->id, $clinic->id, $monday->copy()->setTime(17, 30), $monday->copy()->setTime(18, 0));

    // Um minuto além do fechamento.
    expect(fn () => service()->assertProfessionalAvailable($professional->id, $clinic->id, $monday->copy()->setTime(17, 31), $monday->copy()->setTime(18, 1)))
        ->toThrow(ValidationException::class);
});

// ── Conflitos ──────────────────────────────────────────────────────────

test('booking the exact same start/end as an existing appointment for the same professional is a conflict', function () {
    ['clinic' => $clinic, 'professional' => $professional, 'patient' => $patient] = setupSchedulingServiceContext();
    $monday = nextSchedMonday()->setTime(10, 0);

    Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $professional->id,
        'start' => $monday, 'end' => $monday->copy()->addMinutes(30), 'status' => 'scheduled',
    ]);

    expect(fn () => service()->assertProfessionalAvailable($professional->id, $clinic->id, $monday, $monday->copy()->addMinutes(30)))
        ->toThrow(ValidationException::class, 'Este horário já está ocupado. Escolha outro horário.');
});

test('a one-minute overlap with an existing appointment is still a conflict', function () {
    ['clinic' => $clinic, 'professional' => $professional, 'patient' => $patient] = setupSchedulingServiceContext();
    $monday = nextSchedMonday()->setTime(10, 0);

    Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $professional->id,
        'start' => $monday, 'end' => $monday->copy()->addMinutes(30), 'status' => 'scheduled',
    ]);

    // Novo agendamento começa 1 minuto antes do existente terminar.
    $newStart = $monday->copy()->addMinutes(29);

    expect(fn () => service()->assertProfessionalAvailable($professional->id, $clinic->id, $newStart, $newStart->copy()->addMinutes(30)))
        ->toThrow(ValidationException::class);
});

test('an appointment starting exactly when another ends is NOT a conflict (boundary is exclusive)', function () {
    ['clinic' => $clinic, 'professional' => $professional, 'patient' => $patient] = setupSchedulingServiceContext();
    $monday = nextSchedMonday()->setTime(10, 0);

    Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $professional->id,
        'start' => $monday, 'end' => $monday->copy()->addMinutes(30), 'status' => 'scheduled',
    ]);

    // Novo agendamento começa exatamente quando o existente termina (10:30).
    $newStart = $monday->copy()->addMinutes(30);

    service()->assertProfessionalAvailable($professional->id, $clinic->id, $newStart, $newStart->copy()->addMinutes(30));

    expect(true)->toBeTrue();
});

test('cancelled and no_show appointments never count as a conflict', function () {
    ['clinic' => $clinic, 'professional' => $professional, 'patient' => $patient] = setupSchedulingServiceContext();
    $monday = nextSchedMonday()->setTime(10, 0);

    Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $professional->id,
        'start' => $monday, 'end' => $monday->copy()->addMinutes(30), 'status' => 'cancelled',
    ]);
    Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $professional->id,
        'start' => $monday, 'end' => $monday->copy()->addMinutes(30), 'status' => 'no_show',
    ]);

    service()->assertProfessionalAvailable($professional->id, $clinic->id, $monday, $monday->copy()->addMinutes(30));

    expect(true)->toBeTrue();
});

test('a conflict blocks by professional OR chair, whichever is occupied', function () {
    ['clinic' => $clinic, 'professional' => $professional, 'patient' => $patient] = setupSchedulingServiceContext();
    $chair = \App\Models\Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira A', 'color' => '#111111']);
    $otherProfessional = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($otherProfessional->id, ['role' => 'professional']);

    $monday = nextSchedMonday()->setTime(10, 0);

    // $professional ocupa a cadeira.
    Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $professional->id,
        'chair_id' => $chair->id, 'start' => $monday, 'end' => $monday->copy()->addMinutes(30), 'status' => 'scheduled',
    ]);

    // Outro profissional, mesma cadeira: bloqueado (cadeira ocupada).
    expect(fn () => service()->assertProfessionalAvailable($otherProfessional->id, $clinic->id, $monday, $monday->copy()->addMinutes(30), $chair->id))
        ->toThrow(ValidationException::class);

    // Mesmo profissional, outra cadeira: bloqueado (ele não pode estar em duas cadeiras).
    $chairB = \App\Models\Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira B', 'color' => '#222222']);
    expect(fn () => service()->assertProfessionalAvailable($professional->id, $clinic->id, $monday, $monday->copy()->addMinutes(30), $chairB->id))
        ->toThrow(ValidationException::class);

    // Outro profissional, outra cadeira: livre.
    service()->assertProfessionalAvailable($otherProfessional->id, $clinic->id, $monday, $monday->copy()->addMinutes(30), $chairB->id);
    expect(true)->toBeTrue();
});

test('excludeAppointmentId lets an appointment being edited avoid conflicting with itself', function () {
    ['clinic' => $clinic, 'professional' => $professional, 'patient' => $patient] = setupSchedulingServiceContext();
    $monday = nextSchedMonday()->setTime(10, 0);

    $appointment = Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $professional->id,
        'start' => $monday, 'end' => $monday->copy()->addMinutes(30), 'status' => 'scheduled',
    ]);

    // Sem excludeAppointmentId, "editar" pro mesmo horário conflitaria consigo mesmo.
    expect(fn () => service()->assertProfessionalAvailable($professional->id, $clinic->id, $monday, $monday->copy()->addMinutes(30)))
        ->toThrow(ValidationException::class);

    // Com excludeAppointmentId, não conflita.
    service()->assertProfessionalAvailable($professional->id, $clinic->id, $monday, $monday->copy()->addMinutes(30), null, $appointment->id);
    expect(true)->toBeTrue();
});

// ── dayAvailability ────────────────────────────────────────────────────

test('dayAvailability returns full slots respecting the working-hours window, 15-minute step, and an 8-slot cap', function () {
    ['clinic' => $clinic, 'professional' => $professional] = setupSchedulingServiceContext();
    $professional->clinics()->updateExistingPivot($clinic->id, ['working_start' => '09:00', 'working_end' => '18:00']);
    $monday = nextSchedMonday();
    $pivot = $professional->clinicPivotFor($clinic->id);

    $result = service()->dayAvailability($clinic->id, $clinic, $professional, $pivot, $monday, 30, null);

    expect($result['slots'])->toHaveCount(8)
        ->and($result['slots'][0])->toBe('09:00')
        ->and($result['slots'][1])->toBe('09:15')
        ->and($result['message'])->toBeNull();

    foreach ($result['slots'] as $slot) {
        [$h, $m] = array_map('intval', explode(':', $slot));
        expect($monday->copy()->setTime($h, $m)->addMinutes(30)->lte($monday->copy()->setTime(18, 0)))->toBeTrue();
    }
});

test('dayAvailability without any pivot configuration uses the default 07:00-21:00 window', function () {
    ['clinic' => $clinic] = setupSchedulingServiceContext();
    $outsider = User::factory()->create(['email_verified_at' => now()]);
    $monday = nextSchedMonday();

    $result = service()->dayAvailability($clinic->id, $clinic, $outsider, null, $monday, 30, null);

    expect($result['slots'][0])->toBe('07:00');
});

test('dayAvailability on a day off returns no slots and an explanatory message', function () {
    ['clinic' => $clinic, 'professional' => $professional] = setupSchedulingServiceContext();
    $professional->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => ['mon' => false, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
    ]);
    $monday = nextSchedMonday();
    $pivot = $professional->clinicPivotFor($clinic->id);

    $result = service()->dayAvailability($clinic->id, $clinic, $professional, $pivot, $monday, 30, null);

    expect($result['slots'])->toBe([])
        ->and($result['partial_slots'])->toBe([])
        ->and($result['message'])->toBe('Este profissional não possui atendimento neste dia.');
});

test('dayAvailability on a holiday returns no slots even on an otherwise normal working day', function () {
    ['clinic' => $clinic, 'professional' => $professional] = setupSchedulingServiceContext();
    $nextYear = now()->year + 1;
    $christmas = Carbon::parse("{$nextYear}-12-25");
    $professional->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true),
    ]);
    $clinic->update(['settings' => ['consider_national_holidays' => true]]);
    $pivot = $professional->clinicPivotFor($clinic->id);

    $result = service()->dayAvailability($clinic->id, $clinic, $professional, $pivot, $christmas, 30, null);

    expect($result['slots'])->toBe([])
        ->and($result['message'])->toBe('Este dia está configurado como feriado e não possui atendimento.');
});

// ── partialGaps (via dayAvailability, quando não há slot cheio) ─────────

test('a gap smaller than the requested duration is offered as a partial slot instead of a full one', function () {
    ['clinic' => $clinic, 'professional' => $professional, 'patient' => $patient] = setupSchedulingServiceContext();
    // Janela pequena de propósito: só 09:00-09:30 útil livre depois do bloco ocupado.
    $professional->clinics()->updateExistingPivot($clinic->id, ['working_start' => '09:00', 'working_end' => '10:00']);
    $monday = nextSchedMonday();
    $pivot = $professional->clinicPivotFor($clinic->id);

    // Ocupa 09:30-10:00, deixando só 09:00-09:30 (30min) livre — pedindo
    // duração de 45min, nenhum slot cheio cabe, mas o gap de 30min vira parcial.
    Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $professional->id,
        'start' => $monday->copy()->setTime(9, 30), 'end' => $monday->copy()->setTime(10, 0), 'status' => 'scheduled',
    ]);

    $result = service()->dayAvailability($clinic->id, $clinic, $professional, $pivot, $monday, 45, null);

    expect($result['slots'])->toBe([])
        ->and($result['partial_slots'])->toHaveCount(1)
        ->and($result['partial_slots'][0])->toBe(['start' => '09:00', 'minutes' => 30.0]);
});

test('a gap shorter than 15 minutes is not offered at all, even as a partial slot', function () {
    ['clinic' => $clinic, 'professional' => $professional, 'patient' => $patient] = setupSchedulingServiceContext();
    $professional->clinics()->updateExistingPivot($clinic->id, ['working_start' => '09:00', 'working_end' => '10:00']);
    $monday = nextSchedMonday();
    $pivot = $professional->clinicPivotFor($clinic->id);

    // Ocupa 09:10-10:00, deixando só 09:00-09:10 (10min) livre — abaixo do
    // piso de 15min pra virar um "gap parcial" sugerido.
    Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $professional->id,
        'start' => $monday->copy()->setTime(9, 10), 'end' => $monday->copy()->setTime(10, 0), 'status' => 'scheduled',
    ]);

    $result = service()->dayAvailability($clinic->id, $clinic, $professional, $pivot, $monday, 45, null);

    expect($result['slots'])->toBe([])
        ->and($result['partial_slots'])->toBe([]);
});

test('partial slots are capped at 5 suggestions', function () {
    ['clinic' => $clinic, 'professional' => $professional, 'patient' => $patient] = setupSchedulingServiceContext();
    // Janela de 09:00-12:00 (180min), duração pedida de 40min — cria 6
    // blocos ocupados de 20min intercalados com 6 gaps de 10min... precisa
    // de gaps >=15min pra contar, então usamos blocos que deixam gaps de 20min.
    $professional->clinics()->updateExistingPivot($clinic->id, ['working_start' => '09:00', 'working_end' => '13:00']);
    $monday = nextSchedMonday();
    $pivot = $professional->clinicPivotFor($clinic->id);

    // 7 blocos ocupados de 20min a cada 40min, deixando 6 gaps de 20min —
    // cada gap (20min) é menor que a duração pedida (40min), então todos
    // viram "parcial"; o teto de 5 deve cortar o 6º.
    $cursor = $monday->copy()->setTime(9, 20);
    for ($i = 0; $i < 7; $i++) {
        Appointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $professional->id,
            'start' => $cursor->copy(), 'end' => $cursor->copy()->addMinutes(20), 'status' => 'scheduled',
        ]);
        $cursor->addMinutes(40);
    }

    $result = service()->dayAvailability($clinic->id, $clinic, $professional, $pivot, $monday, 40, null);

    expect($result['slots'])->toBe([])
        ->and(count($result['partial_slots']))->toBeLessThanOrEqual(5);
});

// ── nextAvailableSlot ─────────────────────────────────────────────────

test('nextAvailableSlot finds the first following day with a free slot when the requested day is fully booked', function () {
    ['clinic' => $clinic, 'professional' => $professional, 'patient' => $patient] = setupSchedulingServiceContext();
    $professional->clinics()->updateExistingPivot($clinic->id, ['working_start' => '09:00', 'working_end' => '09:30']);
    $monday = nextSchedMonday();
    $pivot = $professional->clinicPivotFor($clinic->id);

    // A única janela do dia (09:00-09:30) está ocupada.
    Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $professional->id,
        'start' => $monday->copy()->setTime(9, 0), 'end' => $monday->copy()->setTime(9, 30), 'status' => 'scheduled',
    ]);

    $next = service()->nextAvailableSlot($clinic->id, $clinic, $professional, $pivot, $monday, 30, null);

    expect($next)->toBe(['date' => $monday->copy()->addDay()->format('Y-m-d'), 'time' => '09:00']);
});

test('nextAvailableSlot returns null when the professional never works any day of the week (no result within 14 days)', function () {
    ['clinic' => $clinic, 'professional' => $professional] = setupSchedulingServiceContext();
    $professional->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], false),
    ]);
    $monday = nextSchedMonday();
    $pivot = $professional->clinicPivotFor($clinic->id);

    $next = service()->nextAvailableSlot($clinic->id, $clinic, $professional, $pivot, $monday, 30, null);

    expect($next)->toBeNull();
});

// ── Isolamento multi-tenant (C1.1.1) ──────────────────────────────────────
// Achado da fase C1.1: assertNoConflict()/dayAvailability() não tinham
// filtro explícito de clinic_id, dependendo inteiramente de ClinicScope —
// que é inerte durante toda a execução do Pest (app()->runningInConsole()
// é true para o processo inteiro, mesmo dentro de $this->actingAs()->get()
// simulado — a SAPI nunca muda). Um teste de controle provou isso na
// prática: um agendamento da Clínica B "vazava" para uma checagem de
// conflito feita em nome da Clínica A. Em produção (requisição HTTP real,
// SAPI != cli) o ClinicScope aplica normalmente — mas o service foi
// extraído do controller justamente para ser reutilizável, e é exatamente
// esse tipo de classe que tende a ganhar um consumidor futuro fora do
// contexto HTTP (job, comando de console), onde ClinicScope não ajudaria
// em nada. C1.1.1 adicionou clinic_id explícito em ambas as consultas como
// defesa em profundidade — os testes abaixo prendem esse comportamento
// permanentemente.

test('assertNoConflict never sees a same-professional appointment from a different clinic, even without ClinicScope active', function () {
    ['clinic' => $clinicA, 'professional' => $professionalA, 'patient' => $patientA] = setupSchedulingServiceContext();
    ['clinic' => $clinicB, 'patient' => $patientB] = setupSchedulingServiceContext();

    $monday = nextSchedMonday()->setTime(10, 0);

    // Mesmo professional_id (professionalA), mas o agendamento existente
    // pertence à Clínica B — antes de C1.1.1, isso "vazava" e bloqueava a
    // Clínica A por engano.
    Appointment::create([
        'clinic_id' => $clinicB->id, 'patient_id' => $patientB->id, 'professional_id' => $professionalA->id,
        'start' => $monday, 'end' => $monday->copy()->addMinutes(30), 'status' => 'scheduled',
    ]);

    // Clínica A: nenhum conflito real seu, deve passar normalmente.
    service()->assertProfessionalAvailable($professionalA->id, $clinicA->id, $monday, $monday->copy()->addMinutes(30));
    expect(true)->toBeTrue();
});

test('a real conflict within the same clinic still blocks correctly after the explicit clinic_id filter', function () {
    ['clinic' => $clinicA, 'professional' => $professionalA, 'patient' => $patientA] = setupSchedulingServiceContext();

    $monday = nextSchedMonday()->setTime(10, 0);

    Appointment::create([
        'clinic_id' => $clinicA->id, 'patient_id' => $patientA->id, 'professional_id' => $professionalA->id,
        'start' => $monday, 'end' => $monday->copy()->addMinutes(30), 'status' => 'scheduled',
    ]);

    expect(fn () => service()->assertProfessionalAvailable($professionalA->id, $clinicA->id, $monday, $monday->copy()->addMinutes(30)))
        ->toThrow(ValidationException::class);
});

test('dayAvailability never counts a same-professional appointment from a different clinic as occupying a slot', function () {
    ['clinic' => $clinicA, 'professional' => $professionalA, 'patient' => $patientA] = setupSchedulingServiceContext();
    ['clinic' => $clinicB, 'patient' => $patientB] = setupSchedulingServiceContext();
    $professionalA->clinics()->updateExistingPivot($clinicA->id, ['working_start' => '09:00', 'working_end' => '10:00']);
    $monday = nextSchedMonday();
    $pivot = $professionalA->clinicPivotFor($clinicA->id);

    // Ocupa 09:00-09:30 em nome da Clínica B, mesmo profissional.
    Appointment::create([
        'clinic_id' => $clinicB->id, 'patient_id' => $patientB->id, 'professional_id' => $professionalA->id,
        'start' => $monday->copy()->setTime(9, 0), 'end' => $monday->copy()->setTime(9, 30), 'status' => 'scheduled',
    ]);

    $result = service()->dayAvailability($clinicA->id, $clinicA, $professionalA, $pivot, $monday, 30, null);

    // 09:00 continua livre do ponto de vista da Clínica A — o agendamento
    // da Clínica B não deve tirar esse horário da lista.
    expect($result['slots'])->toContain('09:00');
});

test('availableSlots (via the controller, real HTTP request) never lets Clinic B appointments influence Clinic A availability', function () {
    ['clinic' => $clinicA, 'professional' => $professionalA] = setupSchedulingServiceContext();
    ['clinic' => $clinicB, 'patient' => $patientB] = setupSchedulingServiceContext();
    $professionalA->clinics()->updateExistingPivot($clinicA->id, ['working_start' => '09:00', 'working_end' => '10:00']);
    $monday = nextSchedMonday();

    Appointment::create([
        'clinic_id' => $clinicB->id, 'patient_id' => $patientB->id, 'professional_id' => $professionalA->id,
        'start' => $monday->copy()->setTime(9, 0), 'end' => $monday->copy()->setTime(9, 30), 'status' => 'scheduled',
    ]);

    session(['current_clinic_id' => $clinicA->id]);
    $response = test()->actingAs($professionalA)->getJson(route('appointments.available-slots', [
        'professional_id' => $professionalA->id,
        'date' => $monday->toDateString(),
        'duration_minutes' => 30,
    ]))->assertOk();

    expect($response->json('slots'))->toContain('09:00');
});

test('updating an appointment in Clinic A is never blocked by a same-professional appointment belonging to Clinic B', function () {
    ['clinic' => $clinicA, 'professional' => $professionalA, 'patient' => $patientA] = setupSchedulingServiceContext();
    ['clinic' => $clinicB, 'patient' => $patientB] = setupSchedulingServiceContext();
    $monday = nextSchedMonday()->setTime(14, 0);

    $ownAppointment = Appointment::create([
        'clinic_id' => $clinicA->id, 'patient_id' => $patientA->id, 'professional_id' => $professionalA->id,
        'start' => $monday->copy()->setTime(10, 0), 'end' => $monday->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);
    // Mesmo profissional, mesmo horário-alvo (14:00), mas na Clínica B.
    Appointment::create([
        'clinic_id' => $clinicB->id, 'patient_id' => $patientB->id, 'professional_id' => $professionalA->id,
        'start' => $monday, 'end' => $monday->copy()->addMinutes(30), 'status' => 'scheduled',
    ]);

    // Editar o agendamento da Clínica A pra esse mesmo horário (14:00) não
    // deve ser bloqueado pelo agendamento da Clínica B.
    service()->assertProfessionalAvailable(
        $professionalA->id, $clinicA->id, $monday, $monday->copy()->addMinutes(30), null, $ownAppointment->id
    );
    expect(true)->toBeTrue();
});

test('the isolation is symmetric: Clinic B gets the exact same guarantees against Clinic A', function () {
    ['clinic' => $clinicA, 'patient' => $patientA] = setupSchedulingServiceContext();
    ['clinic' => $clinicB, 'professional' => $professionalB, 'patient' => $patientB] = setupSchedulingServiceContext();

    $monday = nextSchedMonday()->setTime(10, 0);

    // Mesmo professional_id (professionalB), agendamento pertence à Clínica A.
    Appointment::create([
        'clinic_id' => $clinicA->id, 'patient_id' => $patientA->id, 'professional_id' => $professionalB->id,
        'start' => $monday, 'end' => $monday->copy()->addMinutes(30), 'status' => 'scheduled',
    ]);

    // Clínica B: sem conflito real, passa normalmente.
    service()->assertProfessionalAvailable($professionalB->id, $clinicB->id, $monday, $monday->copy()->addMinutes(30));

    // Um conflito real dentro da própria Clínica B continua bloqueando.
    Appointment::create([
        'clinic_id' => $clinicB->id, 'patient_id' => $patientB->id, 'professional_id' => $professionalB->id,
        'start' => $monday->copy()->addHours(2), 'end' => $monday->copy()->addHours(2)->addMinutes(30), 'status' => 'scheduled',
    ]);
    expect(fn () => service()->assertProfessionalAvailable($professionalB->id, $clinicB->id, $monday->copy()->addHours(2), $monday->copy()->addHours(2)->addMinutes(30)))
        ->toThrow(ValidationException::class);
});
