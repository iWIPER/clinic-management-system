<?php

use App\Models\Appointment;
use App\Models\AppointmentReturn;
use App\Models\Chair;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\PatientTag;
use App\Models\Plan;
use App\Models\User;

function setupAgendaEvolutionContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-evolution-' . uniqid(),
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
        'name' => 'Clínica Evolução',
        'slug' => 'clinica-evolucao-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $owner = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($owner->id, ['role' => 'owner']);
    $owner->clinics()->updateExistingPivot($clinic->id, ['working_start' => '08:00', 'working_end' => '18:00']);

    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Carla', 'sobrenome' => 'Mendes', 'status' => 'ativo']);
    $chair = Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira 01', 'color' => '#0d9488']);

    session(['current_clinic_id' => $clinic->id]);

    return compact('owner', 'clinic', 'patient', 'chair');
}

function nextMondayEv(): \Illuminate\Support\Carbon
{
    return now()->next(\Carbon\Carbon::MONDAY)->startOfDay();
}

// ── Agendamento sem tratamento, duração livre ───────────────────────────────

test('an appointment can be created without a treatment, using duration_minutes directly', function () {
    ['owner' => $owner, 'patient' => $patient] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $response = $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(),
        'duration_minutes' => 45,
    ])->assertRedirect();

    $appt = Appointment::where('patient_id', $patient->id)->first();
    expect($appt)->not->toBeNull();
    expect($appt->treatment_id)->toBeNull();
    expect($appt->start->format('H:i'))->toBe('10:00');
    expect($appt->end->format('H:i'))->toBe('10:45');
});

test('duration defaults to 30 minutes when omitted entirely', function () {
    ['owner' => $owner, 'patient' => $patient] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(11, 0)->toDateTimeString(),
    ])->assertRedirect();

    $appt = Appointment::where('patient_id', $patient->id)->first();
    expect((int) $appt->start->diffInMinutes($appt->end))->toBe(30);
});

// ── Conflito real no momento de salvar ──────────────────────────────────────

test('store rejects an overlapping appointment for the same professional', function () {
    ['owner' => $owner, 'patient' => $patient] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    Appointment::create([
        'clinic_id' => $owner->clinics()->first()->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0), 'end' => $monday->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);

    $response = $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 15)->toDateTimeString(), 'duration_minutes' => 30,
    ])->assertStatus(422)->assertJsonValidationErrors(['start']);

    expect(Appointment::where('patient_id', $patient->id)->count())->toBe(1);
});

test('update excludes the appointment being edited from its own conflict check', function () {
    ['owner' => $owner, 'patient' => $patient] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $appt = Appointment::create([
        'clinic_id' => $owner->clinics()->first()->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0), 'end' => $monday->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);

    // Reenviar o MESMO horário na edição não pode se autobloquear.
    $this->actingAs($owner)->putJson(route('appointments.update', $appt->id), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(), 'duration_minutes' => 30,
        'status' => 'confirmed', 'notes' => null,
    ])->assertRedirect();

    expect($appt->refresh()->status)->toBe('confirmed');
});

test('no_show is excluded from conflict checks just like cancelled — both free the slot without deleting the record', function () {
    ['owner' => $owner, 'patient' => $patient] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $original = Appointment::create([
        'clinic_id' => $owner->clinics()->first()->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0), 'end' => $monday->copy()->setTime(10, 30), 'status' => 'no_show',
    ]);

    $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(), 'duration_minutes' => 30,
    ])->assertRedirect();

    // O registro antigo (no_show) continua existindo — nunca apagado.
    expect(Appointment::find($original->id))->not->toBeNull();
    expect(Appointment::find($original->id)->status)->toBe('no_show');
    expect(Appointment::where('patient_id', $patient->id)->count())->toBe(2);
});

// ── Etiquetas no agendamento ─────────────────────────────────────────────────

test('appointment tags reuse the existing PatientTag marker catalog and are persisted', function () {
    ['owner' => $owner, 'patient' => $patient, 'clinic' => $clinic] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $tagA = PatientTag::create(['clinic_id' => $clinic->id, 'name' => 'Avaliação', 'slug' => 'avaliacao', 'color' => '#ef4444', 'is_patient_marker' => true]);
    $tagB = PatientTag::create(['clinic_id' => $clinic->id, 'name' => 'Ortodontia', 'slug' => 'ortodontia', 'color' => '#3b82f6', 'is_patient_marker' => true]);

    $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(), 'duration_minutes' => 30,
        'tag_ids' => [$tagA->id, $tagB->id],
    ])->assertRedirect();

    $appt = Appointment::where('patient_id', $patient->id)->first();
    expect($appt->tags()->pluck('patient_tags.id')->sort()->values()->all())->toBe([$tagA->id, $tagB->id]);
});

test('the distinct tag limit is the union of patient markers and tags across all of the patient appointments', function () {
    ['owner' => $owner, 'patient' => $patient, 'clinic' => $clinic] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    // Limite real da constante compartilhada com os marcadores do paciente.
    $limit = \App\Services\PatientMarkerService::MAX_MARKERS_PER_PATIENT;
    $tags = collect(range(1, $limit + 1))->map(fn ($i) => PatientTag::create([
        'clinic_id' => $clinic->id, 'name' => "Tag {$i}", 'slug' => "tag-{$i}", 'color' => '#3b82f6', 'is_patient_marker' => true,
    ]));

    // Paciente já possui as duas primeiras como marcador direto.
    $patient->markers()->sync([$tags[0]->id, $tags[1]->id]);

    // Agendamento tenta adicionar o restante, ultrapassando o teto no total.
    $remaining = $tags->slice(2)->pluck('id')->all(); // limit-1 etiquetas novas, total = limit+1
    $response = $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(), 'duration_minutes' => 30,
        'tag_ids' => $remaining,
    ])->assertStatus(422)->assertJsonValidationErrors(['tag_ids']);

    expect(Appointment::where('patient_id', $patient->id)->count())->toBe(0);

    // Selecionando só o suficiente pra fechar exatamente no limite, passa.
    $exact = $tags->slice(2, $limit - 2)->pluck('id')->all(); // + 2 já existentes = $limit
    $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(11, 0)->toDateTimeString(), 'duration_minutes' => 30,
        'tag_ids' => $exact,
    ])->assertRedirect();

    expect(Appointment::where('patient_id', $patient->id)->count())->toBe(1);
});

// ── Retorno ──────────────────────────────────────────────────────────────────

test('choosing a return option creates a lightweight AppointmentReturn without touching the appointment itself', function () {
    ['owner' => $owner, 'patient' => $patient] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(), 'duration_minutes' => 30,
        'return_option' => '1m', 'return_reason' => 'Controle',
    ])->assertRedirect();

    $appt = Appointment::where('patient_id', $patient->id)->first();
    $return = AppointmentReturn::where('appointment_id', $appt->id)->first();

    expect($return)->not->toBeNull();
    expect($return->reason)->toBe('Controle');
    expect($return->patient_id)->toBe($patient->id);
    expect($return->due_date->format('Y-m-d'))->toBe($monday->copy()->addMonthsNoOverflow(1)->format('Y-m-d'));
    // Nada foi criado como um segundo Appointment.
    expect(Appointment::where('patient_id', $patient->id)->count())->toBe(1);
});

test('"Sem retorno" (the default) creates no AppointmentReturn at all', function () {
    ['owner' => $owner, 'patient' => $patient] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(), 'duration_minutes' => 30,
    ])->assertRedirect();

    expect(AppointmentReturn::count())->toBe(0);
});

// ── Confirmação (intenção, nunca envio real) ─────────────────────────────────

test('confirmation_requested only stores the preference, never a send confirmation', function () {
    ['owner' => $owner, 'patient' => $patient] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(), 'duration_minutes' => 30,
        'confirmation_requested' => true,
    ])->assertRedirect();

    $appt = Appointment::where('patient_id', $patient->id)->first();
    expect($appt->confirmation_requested)->toBeTrue();
});

// ── Disponibilidade multi-dia ────────────────────────────────────────────────

test('availableSlots finds the next full slot on a following day when the requested day has none', function () {
    ['owner' => $owner, 'patient' => $patient] = setupAgendaEvolutionContext();
    $owner->clinics()->updateExistingPivot($owner->clinics()->first()->id, ['working_start' => '09:00', 'working_end' => '09:30']);
    $monday = nextMondayEv();

    // Dia inteiro (a única janela do dia) ocupado.
    Appointment::create([
        'clinic_id' => $owner->clinics()->first()->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(9, 0), 'end' => $monday->copy()->setTime(9, 30), 'status' => 'scheduled',
    ]);

    $response = $this->actingAs($owner)->getJson(route('appointments.available-slots', [
        'professional_id' => $owner->id, 'date' => $monday->toDateString(), 'duration_minutes' => 30,
    ]))->assertOk();

    expect($response->json('slots'))->toBe([]);
    expect($response->json('next_available.date'))->toBe($monday->copy()->addDay()->format('Y-m-d'));
    expect($response->json('next_available.time'))->toBe('09:00');
});

// ── Limites de caracteres (frontend não é a única defesa) ───────────────────

test('store rejects notes over 200 characters', function () {
    ['owner' => $owner, 'patient' => $patient] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(), 'duration_minutes' => 30,
        'notes' => str_repeat('a', 201),
    ])->assertStatus(422)->assertJsonValidationErrors(['notes']);

    expect(Appointment::where('patient_id', $patient->id)->count())->toBe(0);
});

test('store accepts notes at exactly 200 characters', function () {
    ['owner' => $owner, 'patient' => $patient] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(), 'duration_minutes' => 30,
        'notes' => str_repeat('a', 200),
    ])->assertRedirect();

    expect(Appointment::where('patient_id', $patient->id)->count())->toBe(1);
});

test('update rejects notes over 200 characters', function () {
    ['owner' => $owner, 'patient' => $patient] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $appt = Appointment::create([
        'clinic_id' => $owner->clinics()->first()->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0), 'end' => $monday->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);

    $this->actingAs($owner)->putJson(route('appointments.update', $appt->id), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(), 'duration_minutes' => 30,
        'status' => 'scheduled', 'notes' => str_repeat('a', 201),
    ])->assertStatus(422)->assertJsonValidationErrors(['notes']);
});

test('return_reason over 500 characters is rejected on both store and update', function () {
    ['owner' => $owner, 'patient' => $patient] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(), 'duration_minutes' => 30,
        'return_option' => '1m', 'return_reason' => str_repeat('a', 501),
    ])->assertStatus(422)->assertJsonValidationErrors(['return_reason']);

    $appt = Appointment::create([
        'clinic_id' => $owner->clinics()->first()->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(11, 0), 'end' => $monday->copy()->setTime(11, 30), 'status' => 'scheduled',
    ]);
    $this->actingAs($owner)->putJson(route('appointments.update', $appt->id), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(11, 0)->toDateTimeString(), 'duration_minutes' => 30,
        'status' => 'scheduled', 'return_option' => '1m', 'return_reason' => str_repeat('a', 501),
    ])->assertStatus(422)->assertJsonValidationErrors(['return_reason']);
});

// ── Retorno na edição ────────────────────────────────────────────────────────

test('update creates a return when none existed yet', function () {
    ['owner' => $owner, 'patient' => $patient] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $appt = Appointment::create([
        'clinic_id' => $owner->clinics()->first()->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0), 'end' => $monday->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);
    expect(AppointmentReturn::where('appointment_id', $appt->id)->exists())->toBeFalse();

    $this->actingAs($owner)->putJson(route('appointments.update', $appt->id), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(), 'duration_minutes' => 30,
        'status' => 'scheduled', 'return_option' => '15d', 'return_reason' => 'Controle',
    ])->assertRedirect();

    $return = AppointmentReturn::where('appointment_id', $appt->id)->first();
    expect($return)->not->toBeNull();
    expect($return->reason)->toBe('Controle');
    expect($return->due_date->format('Y-m-d'))->toBe($monday->copy()->addDays(15)->format('Y-m-d'));
});

test('update changes an existing return in place instead of duplicating it', function () {
    ['owner' => $owner, 'patient' => $patient, 'clinic' => $clinic] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $appt = Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0), 'end' => $monday->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);
    $existing = AppointmentReturn::create([
        'clinic_id' => $clinic->id, 'appointment_id' => $appt->id, 'patient_id' => $patient->id,
        'professional_id' => $owner->id, 'due_date' => $monday->copy()->addDays(15), 'reason' => 'Controle inicial', 'status' => 'pending',
    ]);

    $this->actingAs($owner)->putJson(route('appointments.update', $appt->id), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(), 'duration_minutes' => 30,
        'status' => 'scheduled', 'return_option' => '6m', 'return_reason' => 'Motivo atualizado',
    ])->assertRedirect();

    expect(AppointmentReturn::where('appointment_id', $appt->id)->count())->toBe(1);
    $return = AppointmentReturn::find($existing->id);
    expect($return->reason)->toBe('Motivo atualizado');
    expect($return->due_date->format('Y-m-d'))->toBe($monday->copy()->addMonthsNoOverflow(6)->format('Y-m-d'));
});

test('update removes an existing return when set back to "Sem retorno"', function () {
    ['owner' => $owner, 'patient' => $patient, 'clinic' => $clinic] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $appt = Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0), 'end' => $monday->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);
    AppointmentReturn::create([
        'clinic_id' => $clinic->id, 'appointment_id' => $appt->id, 'patient_id' => $patient->id,
        'professional_id' => $owner->id, 'due_date' => $monday->copy()->addDays(15), 'reason' => null, 'status' => 'pending',
    ]);

    $this->actingAs($owner)->putJson(route('appointments.update', $appt->id), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(), 'duration_minutes' => 30,
        'status' => 'scheduled', 'return_option' => 'none',
    ])->assertRedirect();

    expect(AppointmentReturn::where('appointment_id', $appt->id)->exists())->toBeFalse();
    // O agendamento em si continua existindo, intocado.
    expect(Appointment::find($appt->id))->not->toBeNull();
});

test('update without sending return_option at all leaves an existing return untouched', function () {
    ['owner' => $owner, 'patient' => $patient, 'clinic' => $clinic] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $appt = Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0), 'end' => $monday->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);
    AppointmentReturn::create([
        'clinic_id' => $clinic->id, 'appointment_id' => $appt->id, 'patient_id' => $patient->id,
        'professional_id' => $owner->id, 'due_date' => $monday->copy()->addDays(15), 'reason' => 'Original', 'status' => 'pending',
    ]);

    // Payload sem return_option — simula um caller que não mexe nesse campo.
    $this->actingAs($owner)->putJson(route('appointments.update', $appt->id), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0)->toDateTimeString(), 'duration_minutes' => 30,
        'status' => 'confirmed',
    ])->assertRedirect();

    expect(AppointmentReturn::where('appointment_id', $appt->id)->where('reason', 'Original')->exists())->toBeTrue();
});

// ── Payload da tela de edição ────────────────────────────────────────────────

test('the edit page payload carries tags, appointment_return, marker catalog/limit and no longer carries treatments', function () {
    ['owner' => $owner, 'patient' => $patient, 'clinic' => $clinic] = setupAgendaEvolutionContext();
    $monday = nextMondayEv();

    $tag = PatientTag::create(['clinic_id' => $clinic->id, 'name' => 'Avaliação', 'slug' => 'avaliacao-edit', 'color' => '#ef4444', 'is_patient_marker' => true]);
    $appt = Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'start' => $monday->copy()->setTime(10, 0), 'end' => $monday->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);
    $appt->tags()->sync([$tag->id]);

    $this->actingAs($owner)->get(route('appointments.edit', $appt->id))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Appointments/Edit')
        ->has('appointment.tags', 1)
        ->where('appointment.tags.0.name', 'Avaliação')
        ->has('availableMarkers')
        ->where('markerLimit', \App\Services\PatientMarkerService::MAX_MARKERS_PER_PATIENT)
        ->missing('treatments')
    );
});
