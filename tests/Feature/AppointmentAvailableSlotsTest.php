<?php

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Treatment;
use App\Models\User;

function setupAppointmentSlotsContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-slots-' . uniqid(),
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
        'name' => 'Clínica Horários',
        'slug' => 'clinica-horarios-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $owner = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($owner->id, ['role' => 'owner']);
    $owner->clinics()->updateExistingPivot($clinic->id, ['working_start' => '09:00', 'working_end' => '12:00']);

    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'João', 'sobrenome' => 'Silva', 'status' => 'ativo', 'telefone' => '11988887777', 'cpf' => '12345678901']);

    $treatment = Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Consulta', 'tipo' => 'procedimento',
        'duracao_padrao' => 30, 'preco_base' => 100, 'ativo' => true,
    ]);

    session(['current_clinic_id' => $clinic->id]);

    return compact('owner', 'clinic', 'patient', 'treatment');
}

function nextMonday(): \Illuminate\Support\Carbon
{
    return now()->next(\Carbon\Carbon::MONDAY)->startOfDay();
}

// ── Sugestões básicas ────────────────────────────────────────────────────────

test('available slots respect the working-hours window and 15-minute step', function () {
    ['owner' => $owner] = setupAppointmentSlotsContext();
    $monday = nextMonday();

    $response = $this->actingAs($owner)->getJson(route('appointments.available-slots', [
        'professional_id' => $owner->id,
        'date' => $monday->toDateString(),
        'duration_minutes' => 30,
    ]))->assertOk();

    $slots = $response->json('slots');
    expect($slots)->not->toBeEmpty();
    expect($slots[0])->toBe('09:00');
    expect($slots)->each->toMatch('/^\d{2}:\d{2}$/');
    // 30min de duração dentro de 09:00-12:00 nunca sugere algo que passe das 12:00.
    foreach ($slots as $slot) {
        [$h, $m] = array_map('intval', explode(':', $slot));
        $end = $monday->copy()->setTime($h, $m)->addMinutes(30);
        expect($end->lte($monday->copy()->setTime(12, 0)))->toBeTrue();
    }
});

test('an existing non-cancelled appointment removes its overlapping slots, but a cancelled one does not', function () {
    ['owner' => $owner, 'clinic' => $clinic, 'patient' => $patient, 'treatment' => $treatment] = setupAppointmentSlotsContext();
    $monday = nextMonday();

    Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'treatment_id' => $treatment->id, 'start' => $monday->copy()->setTime(10, 0),
        'end' => $monday->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);
    Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'treatment_id' => $treatment->id, 'start' => $monday->copy()->setTime(10, 30),
        'end' => $monday->copy()->setTime(11, 0), 'status' => 'cancelled',
    ]);

    $slots = $this->actingAs($owner)->getJson(route('appointments.available-slots', [
        'professional_id' => $owner->id,
        'date' => $monday->toDateString(),
        'duration_minutes' => 30,
    ]))->assertOk()->json('slots');

    expect($slots)->not->toContain('10:00');
    // Cancelado não bloqueia — 10:30 continua sugerido.
    expect($slots)->toContain('10:30');
});

test('a conflict blocks by professional OR chair, whichever is occupied — a professional cannot be in two chairs at once', function () {
    ['owner' => $owner, 'clinic' => $clinic, 'patient' => $patient, 'treatment' => $treatment] = setupAppointmentSlotsContext();
    $monday = nextMonday();

    $chairA = \App\Models\Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira A', 'color' => '#111111']);
    $chairB = \App\Models\Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira B', 'color' => '#222222']);
    // Mesma janela estreita do $owner — sem isso, a janela padrão bem mais
    // larga (07:00-21:00, sem horário configurado) empurra 10:00 pra fora
    // do teto de 8 sugestões, mascarando o que este teste quer provar.
    $other = \App\Models\User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($other->id, ['role' => 'professional']);
    $other->clinics()->updateExistingPivot($clinic->id, ['working_start' => '09:00', 'working_end' => '12:00']);

    // $owner ocupa a cadeira A às 10:00.
    Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'treatment_id' => $treatment->id, 'chair_id' => $chairA->id,
        'start' => $monday->copy()->setTime(10, 0), 'end' => $monday->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);

    // Mesmo profissional, cadeira B: continua ocupado (não pode estar em
    // dois lugares ao mesmo tempo), mesmo a cadeira em si estando livre.
    $sameProBOtherChair = $this->actingAs($owner)->getJson(route('appointments.available-slots', [
        'professional_id' => $owner->id, 'date' => $monday->toDateString(), 'duration_minutes' => 30, 'chair_id' => $chairB->id,
    ]))->assertOk()->json('slots');
    expect($sameProBOtherChair)->not->toContain('10:00');

    // Profissional diferente, mesma cadeira A: cadeira ocupada bloqueia
    // mesmo sendo outro profissional.
    $otherProSameChair = $this->actingAs($owner)->getJson(route('appointments.available-slots', [
        'professional_id' => $other->id, 'date' => $monday->toDateString(), 'duration_minutes' => 30, 'chair_id' => $chairA->id,
    ]))->assertOk()->json('slots');
    expect($otherProSameChair)->not->toContain('10:00');

    // Profissional diferente, cadeira diferente: nada em comum, livre.
    $otherProOtherChair = $this->actingAs($owner)->getJson(route('appointments.available-slots', [
        'professional_id' => $other->id, 'date' => $monday->toDateString(), 'duration_minutes' => 30, 'chair_id' => $chairB->id,
    ]))->assertOk()->json('slots');
    expect($otherProOtherChair)->toContain('10:00');
});

// ── Feriado e dia de folga ───────────────────────────────────────────────────

test('a holiday returns no slots and an explanatory message when the clinic setting is on', function () {
    ['owner' => $owner, 'clinic' => $clinic] = setupAppointmentSlotsContext();
    $clinic->update(['settings' => ['consider_national_holidays' => true]]);

    $nextYear = now()->year + 1;
    $tiradentes = \Carbon\Carbon::parse("{$nextYear}-04-21");
    $dayKey = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'][$tiradentes->dayOfWeekIso - 1];
    $owner->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true),
    ]);

    $response = $this->actingAs($owner)->getJson(route('appointments.available-slots', [
        'professional_id' => $owner->id,
        'date' => $tiradentes->toDateString(),
        'duration_minutes' => 30,
    ]))->assertOk();

    expect($response->json('slots'))->toBe([]);
    expect($response->json('message'))->not->toBeEmpty();
    // Prova que quem bloqueou foi o feriado, não o dia da semana.
    expect($owner->clinicPivotFor($clinic->id)->working_days[$dayKey])->toBeTrue();
});

test('a day the professional does not work returns no slots and an explanatory message', function () {
    ['owner' => $owner, 'clinic' => $clinic] = setupAppointmentSlotsContext();
    $monday = nextMonday();

    $owner->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => array_merge(array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true), ['mon' => false]),
    ]);

    $response = $this->actingAs($owner)->getJson(route('appointments.available-slots', [
        'professional_id' => $owner->id,
        'date' => $monday->toDateString(),
        'duration_minutes' => 30,
    ]))->assertOk();

    expect($response->json('slots'))->toBe([]);
    expect($response->json('message'))->not->toBeEmpty();
});

// ── Busca de pacientes por telefone/CPF ──────────────────────────────────────

test('patient search also matches by phone and CPF, not just name', function () {
    ['owner' => $owner, 'patient' => $patient] = setupAppointmentSlotsContext();

    $byPhone = $this->actingAs($owner)->getJson(route('patients.search', ['q' => '988887777']))->assertOk()->json();
    $byCpf   = $this->actingAs($owner)->getJson(route('patients.search', ['q' => '12345678901']))->assertOk()->json();

    expect(collect($byPhone)->pluck('id'))->toContain($patient->id);
    expect(collect($byCpf)->pluck('id'))->toContain($patient->id);
});
