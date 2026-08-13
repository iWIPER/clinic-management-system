<?php

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Treatment;
use App\Models\User;

function setupAgendaAvailabilityContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-avail-' . uniqid(),
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
        'name' => 'Clínica Agendas',
        'slug' => 'clinica-agendas-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    // Owner sem job_title definido de propósito — cobre a regra: o dono
    // aparece nas Agendas mesmo sem ter passado pelo fluxo de convite.
    $owner = User::factory()->create(['email_verified_at' => now(), 'job_title' => null, 'status' => 'ativo']);
    $clinic->users()->attach($owner->id, ['role' => 'owner']);

    $colleague = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($colleague->id, ['role' => 'professional']);

    $staff = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Secretário(a)', 'status' => 'ativo']);
    $clinic->users()->attach($staff->id, ['role' => 'staff']);

    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'João', 'sobrenome' => 'Silva', 'status' => 'ativo']);

    $treatment = Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Consulta', 'tipo' => 'procedimento',
        'duracao_padrao' => 30, 'preco_base' => 100, 'ativo' => true,
    ]);

    session(['current_clinic_id' => $clinic->id]);

    return compact('owner', 'colleague', 'staff', 'clinic', 'patient', 'treatment');
}

// Próxima segunda/sábado/domingo a partir de "agora", pra sempre cair no dia
// da semana certo independente de quando os testes rodam.
function nextWeekday(int $isoDayOfWeek): \Illuminate\Support\Carbon
{
    return now()->next($isoDayOfWeek)->setTime(10, 0);
}

// ── Ordenação da lista de Agendas ───────────────────────────────────────────

test('the logged-in user appears first in the professionals list, followed by others, owner included despite empty job_title', function () {
    ['owner' => $owner, 'colleague' => $colleague] = setupAgendaAvailabilityContext();

    $response = $this->actingAs($colleague)->get(route('appointments.index'))->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->has('professionals', 2) // owner + colleague — staff (Secretário) fica de fora
        ->where('professionals.0.id', $colleague->id)   // quem está logado vem primeiro
        ->where('professionals.0.is_current_user', true)
        ->where('professionals.1.id', $owner->id)        // dono aparece mesmo com job_title vazio
        ->where('professionals.1.is_current_user', false)
    );

    // Login como o dono agora — ele passa a ser o primeiro.
    $response2 = $this->actingAs($owner)->get(route('appointments.index'))->assertOk();
    $response2->assertInertia(fn ($page) => $page
        ->where('professionals.0.id', $owner->id)
        ->where('professionals.1.id', $colleague->id)
    );
});

test('a non-clinical staff member (Secretário) does not appear in the Agendas list', function () {
    ['owner' => $owner, 'colleague' => $colleague] = setupAgendaAvailabilityContext();

    // setupAgendaAvailabilityContext() cria 3 pessoas na clínica: dono,
    // colega Dentista e uma Secretária. Só as 2 primeiras têm agenda
    // clínica — a contagem exata em 2 já prova que a Secretária ficou de
    // fora (senão seria 3).
    $this->actingAs($owner)->get(route('appointments.index'))->assertInertia(fn ($page) => $page
        ->has('professionals', 2)
        ->where('professionals.0.id', $owner->id)
        ->where('professionals.1.id', $colleague->id)
    );
});

// ── Dias de trabalho ─────────────────────────────────────────────────────────

test('a professional can enable and disable individual working days', function () {
    ['colleague' => $colleague] = setupAgendaAvailabilityContext();

    $this->actingAs($colleague)
        ->putJson(route('clinic-settings.agendas.update', $colleague->id), [
            'agenda_visible_to_team' => true,
            'working_days' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => false, 'sun' => false],
            'working_start' => '09:00',
            'working_end' => '18:00',
        ])
        ->assertOk();

    $pivot = $colleague->clinicPivotFor(session('current_clinic_id'));
    expect($pivot->working_days['sat'])->toBeFalse();
    expect($pivot->working_days['fri'])->toBeTrue();
});

test('saturday off really blocks creating an appointment for that professional on saturday, and the backend is authoritative', function () {
    ['colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $colleague->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => false, 'sun' => true],
    ]);

    $saturday = nextWeekday(\Carbon\Carbon::SATURDAY);

    $this->actingAs($colleague)
        ->postJson(route('appointments.store'), [
            'patient_id' => $patient->id, 'professional_id' => $colleague->id, 'treatment_id' => $treatment->id,
            'start' => $saturday->toDateTimeString(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['start']);

    expect(Appointment::where('professional_id', $colleague->id)->count())->toBe(0);
});

test('sunday off really blocks creating an appointment for that professional on sunday', function () {
    ['colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $colleague->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => false],
    ]);

    $sunday = nextWeekday(\Carbon\Carbon::SUNDAY);

    $this->actingAs($colleague)
        ->postJson(route('appointments.store'), [
            'patient_id' => $patient->id, 'professional_id' => $colleague->id, 'treatment_id' => $treatment->id,
            'start' => $sunday->toDateTimeString(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['start']);
});

test('a professional with saturday on can be booked normally on saturday', function () {
    ['colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $colleague->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => false],
    ]);

    $saturday = nextWeekday(\Carbon\Carbon::SATURDAY);

    $this->actingAs($colleague)
        ->postJson(route('appointments.store'), [
            'patient_id' => $patient->id, 'professional_id' => $colleague->id, 'treatment_id' => $treatment->id,
            'start' => $saturday->toDateTimeString(),
        ])
        ->assertRedirect();

    expect(Appointment::where('professional_id', $colleague->id)->count())->toBe(1);
});

test('updating an appointment to an off day for that professional is also blocked', function () {
    ['owner' => $owner, 'colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $colleague->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => false, 'sun' => true],
    ]);

    $appointment = Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $colleague->id,
        'treatment_id' => $treatment->id, 'start' => nextWeekday(\Carbon\Carbon::MONDAY), 'end' => nextWeekday(\Carbon\Carbon::MONDAY)->addMinutes(30),
        'status' => 'scheduled',
    ]);

    $saturday = nextWeekday(\Carbon\Carbon::SATURDAY);

    $this->actingAs($owner)
        ->putJson(route('appointments.update', $appointment->id), [
            'patient_id' => $patient->id, 'professional_id' => $colleague->id, 'treatment_id' => $treatment->id,
            'start' => $saturday->toDateTimeString(), 'status' => 'scheduled',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['start']);
});

test('two professionals can have different saturday/sunday configurations at the same time', function () {
    ['owner' => $owner, 'colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $owner->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => false, 'sun' => false],
    ]);
    $colleague->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => false],
    ]);

    $saturday = nextWeekday(\Carbon\Carbon::SATURDAY);

    // Dono não atende sábado: bloqueado.
    $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id, 'treatment_id' => $treatment->id,
        'start' => $saturday->toDateTimeString(),
    ])->assertStatus(422);

    // Colega atende sábado: permitido.
    $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $colleague->id, 'treatment_id' => $treatment->id,
        'start' => $saturday->toDateTimeString(),
    ])->assertRedirect();

    expect(Appointment::where('professional_id', $owner->id)->count())->toBe(0);
    expect(Appointment::where('professional_id', $colleague->id)->count())->toBe(1);
});

// ── Horário de atendimento ───────────────────────────────────────────────────

test('a professional can configure their working hours (09:00-18:00), stored and resolved correctly', function () {
    ['colleague' => $colleague] = setupAgendaAvailabilityContext();

    $this->actingAs($colleague)
        ->putJson(route('clinic-settings.agendas.update', $colleague->id), [
            'agenda_visible_to_team' => true,
            'working_days' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => false, 'sun' => false],
            'working_start' => '09:00',
            'working_end' => '18:00',
        ])
        ->assertOk();

    $pivot = $colleague->clinicPivotFor(session('current_clinic_id'));
    expect($pivot->workingHoursResolved())->toBe(['start' => '09:00', 'end' => '18:00']);
});

test('working_end must be after working_start', function () {
    ['colleague' => $colleague] = setupAgendaAvailabilityContext();

    $this->actingAs($colleague)
        ->putJson(route('clinic-settings.agendas.update', $colleague->id), [
            'agenda_visible_to_team' => true,
            'working_days' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'working_start' => '18:00',
            'working_end' => '09:00',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['working_end']);
});

test('a professional without configured hours has no working-hours restriction at all — same spirit as working_days unset', function () {
    ['colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment] = setupAgendaAvailabilityContext();

    $pivot = $colleague->clinicPivotFor(session('current_clinic_id'));
    // Sem restrição real (o que de fato bloqueia agendamento) — nunca ficou
    // silenciosamente travado em 09-18 sem o profissional ter configurado nada.
    expect($pivot->workingHoursConfigured())->toBeNull();
    // A UI de configurações, porém, ainda mostra um valor sugerido nos campos.
    expect($pivot->workingHoursResolved())->toBe(['start' => '09:00', 'end' => '18:00']);

    // Confirma na prática: agendamento bem fora de 09-18 (22h) passa normalmente.
    $lateMonday = nextWeekday(\Carbon\Carbon::MONDAY)->setTime(22, 0);
    $this->actingAs($colleague)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $colleague->id, 'treatment_id' => $treatment->id,
        'start' => $lateMonday->toDateTimeString(),
    ])->assertRedirect();

    expect(Appointment::where('professional_id', $colleague->id)->count())->toBe(1);
});

test('booking before the configured start time is blocked with a friendly message', function () {
    ['colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $colleague->clinics()->updateExistingPivot($clinic->id, ['working_start' => '09:00', 'working_end' => '18:00']);

    $monday = nextWeekday(\Carbon\Carbon::MONDAY)->setTime(8, 30);

    $response = $this->actingAs($colleague)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $colleague->id, 'treatment_id' => $treatment->id,
        'start' => $monday->toDateTimeString(),
    ])->assertStatus(422)->assertJsonValidationErrors(['start']);

    expect($response->json('errors.start.0'))->toBe('Este horário está fora do horário de atendimento deste profissional.');
    expect(Appointment::where('professional_id', $colleague->id)->count())->toBe(0);
});

test('booking after the configured end time is blocked (appointment would run past closing)', function () {
    ['colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $colleague->clinics()->updateExistingPivot($clinic->id, ['working_start' => '09:00', 'working_end' => '18:00']);

    // Tratamento tem 30min — 17:45 termina 18:15, passa do fechamento.
    $monday = nextWeekday(\Carbon\Carbon::MONDAY)->setTime(17, 45);

    $this->actingAs($colleague)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $colleague->id, 'treatment_id' => $treatment->id,
        'start' => $monday->toDateTimeString(),
    ])->assertStatus(422)->assertJsonValidationErrors(['start']);

    expect(Appointment::where('professional_id', $colleague->id)->count())->toBe(0);
});

test('booking exactly at the boundaries (start and last possible slot) is allowed', function () {
    ['colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $colleague->clinics()->updateExistingPivot($clinic->id, ['working_start' => '09:00', 'working_end' => '18:00']);

    $monday = nextWeekday(\Carbon\Carbon::MONDAY)->setTime(9, 0);
    $this->actingAs($colleague)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $colleague->id, 'treatment_id' => $treatment->id,
        'start' => $monday->toDateTimeString(),
    ])->assertRedirect();

    // 30min treatment, 17:30-18:00 cabe exatamente até o fechamento.
    $sameMonday = nextWeekday(\Carbon\Carbon::MONDAY)->setTime(17, 30);
    $this->actingAs($colleague)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $colleague->id, 'treatment_id' => $treatment->id,
        'start' => $sameMonday->toDateTimeString(),
    ])->assertRedirect();

    expect(Appointment::where('professional_id', $colleague->id)->count())->toBe(2);
});

test('different professionals can have different working hours enforced independently at the same time slot', function () {
    ['owner' => $owner, 'colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $owner->clinics()->updateExistingPivot($clinic->id, ['working_start' => '09:00', 'working_end' => '18:00']);
    $colleague->clinics()->updateExistingPivot($clinic->id, ['working_start' => '08:00', 'working_end' => '17:00']);

    $monday = nextWeekday(\Carbon\Carbon::MONDAY)->setTime(8, 15);

    // Dono (09-18) bloqueado às 8:15.
    $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $owner->id, 'treatment_id' => $treatment->id,
        'start' => $monday->toDateTimeString(),
    ])->assertStatus(422);

    // Colega (08-17) permitido às 8:15.
    $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $colleague->id, 'treatment_id' => $treatment->id,
        'start' => $monday->toDateTimeString(),
    ])->assertRedirect();

    expect(Appointment::where('professional_id', $owner->id)->count())->toBe(0);
    expect(Appointment::where('professional_id', $colleague->id)->count())->toBe(1);
});

test('updating an appointment to a time outside working hours is also blocked', function () {
    ['owner' => $owner, 'colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $colleague->clinics()->updateExistingPivot($clinic->id, ['working_start' => '09:00', 'working_end' => '18:00']);

    $appointment = Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $colleague->id,
        'treatment_id' => $treatment->id, 'start' => nextWeekday(\Carbon\Carbon::MONDAY)->setTime(10, 0),
        'end' => nextWeekday(\Carbon\Carbon::MONDAY)->setTime(10, 30), 'status' => 'scheduled',
    ]);

    $tooLate = nextWeekday(\Carbon\Carbon::MONDAY)->setTime(19, 0);

    $this->actingAs($owner)
        ->putJson(route('appointments.update', $appointment->id), [
            'patient_id' => $patient->id, 'professional_id' => $colleague->id, 'treatment_id' => $treatment->id,
            'start' => $tooLate->toDateTimeString(), 'status' => 'scheduled',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['start']);
});

test('the Agenda payload exposes working_hours per professional (index, fullscreen, and the create form)', function () {
    ['owner' => $owner, 'colleague' => $colleague, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $colleague->clinics()->updateExistingPivot($clinic->id, ['working_start' => '08:00', 'working_end' => '17:00']);

    $this->actingAs($owner)->get(route('appointments.index'))->assertInertia(fn ($page) => $page
        ->where('professionals.1.working_hours', ['start' => '08:00', 'end' => '17:00'])
    );

    $this->actingAs($owner)->get(route('appointments.fullscreen'))->assertInertia(fn ($page) => $page
        ->where('professionals.1.working_hours', ['start' => '08:00', 'end' => '17:00'])
    );

    $this->actingAs($owner)->get(route('appointments.create'))->assertInertia(fn ($page) => $page
        ->has('considerNationalHolidays')
        ->has('holidays')
    );
});

// ── Feriados nacionais ────────────────────────────────────────────────────────

test('only owner/admin can toggle the clinic-wide national holidays setting', function () {
    ['colleague' => $colleague] = setupAgendaAvailabilityContext();

    $this->actingAs($colleague)
        ->putJson(route('clinic-settings.agendas.holidays'), ['consider_national_holidays' => true])
        ->assertStatus(403);
});

test('owner can turn national holidays on, and it persists on the clinic without clobbering other settings', function () {
    ['owner' => $owner, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $clinic->update(['settings' => ['some_other_key' => 'preserved']]);

    $this->actingAs($owner)
        ->putJson(route('clinic-settings.agendas.holidays'), ['consider_national_holidays' => true])
        ->assertOk();

    $clinic->refresh();
    expect($clinic->considersNationalHolidays())->toBeTrue();
    expect($clinic->settings['some_other_key'])->toBe('preserved');
});

test('booking on a national holiday is blocked when the clinic setting is ON, even on a normal working day', function () {
    ['owner' => $owner, 'colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    // Feriado de data fixa e independente de qual dia da semana caia neste
    // ano — descobre o weekday real e libera esse dia pro profissional,
    // pra provar que o feriado bloqueia mesmo sendo um "dia de atendimento".
    $nextYear = now()->year + 1;
    $tiradentes = \Carbon\Carbon::parse("{$nextYear}-04-21");
    $dayKey = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'][$tiradentes->dayOfWeekIso - 1];

    $colleague->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true),
        'working_start' => '00:00', 'working_end' => '23:59',
    ]);
    $clinic->update(['settings' => ['consider_national_holidays' => true]]);

    $response = $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $colleague->id, 'treatment_id' => $treatment->id,
        'start' => $tiradentes->copy()->setTime(10, 0)->toDateTimeString(),
    ])->assertStatus(422)->assertJsonValidationErrors(['start']);

    expect($response->json('errors.start.0'))->toBe('Este dia está configurado como feriado e não possui atendimento.');
    expect(Appointment::where('professional_id', $colleague->id)->count())->toBe(0);
    // Confere que $dayKey de fato estava "true" — prova que quem bloqueou foi o feriado, não o dia da semana.
    expect($colleague->clinicPivotFor($clinic->id)->working_days[$dayKey])->toBeTrue();
});

test('the same national holiday date is bookable normally when the clinic setting is OFF', function () {
    ['owner' => $owner, 'colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $nextYear = now()->year + 1;
    $tiradentes = \Carbon\Carbon::parse("{$nextYear}-04-21");

    $colleague->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true),
        'working_start' => '00:00', 'working_end' => '23:59',
    ]);
    // consider_national_holidays permanece OFF (default).

    $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $colleague->id, 'treatment_id' => $treatment->id,
        'start' => $tiradentes->copy()->setTime(10, 0)->toDateTimeString(),
    ])->assertRedirect();

    expect(Appointment::where('professional_id', $colleague->id)->count())->toBe(1);
});

test('holiday takes precedence over an otherwise normal working day/hours — the holiday message wins', function () {
    ['owner' => $owner, 'colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $nextYear = now()->year + 1;
    $christmas = \Carbon\Carbon::parse("{$nextYear}-12-25");
    $dayKey = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'][$christmas->dayOfWeekIso - 1];

    // Profissional configurado pra atender exatamente nesse dia da semana,
    // em horário comercial padrão — nada, a não ser o feriado, bloquearia.
    $colleague->clinics()->updateExistingPivot($clinic->id, [
        'working_days' => [$dayKey => true] + array_fill_keys(
            array_diff(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], [$dayKey]), true
        ),
        'working_start' => '09:00', 'working_end' => '18:00',
    ]);
    $clinic->update(['settings' => ['consider_national_holidays' => true]]);

    $response = $this->actingAs($owner)->postJson(route('appointments.store'), [
        'patient_id' => $patient->id, 'professional_id' => $colleague->id, 'treatment_id' => $treatment->id,
        'start' => $christmas->copy()->setTime(10, 0)->toDateTimeString(),
    ])->assertStatus(422);

    expect($response->json('errors.start.0'))->toBe('Este dia está configurado como feriado e não possui atendimento.');
});

// ── Visibilidade da agenda ───────────────────────────────────────────────────

test('a professional can make their agenda visible and remove that visibility again', function () {
    ['colleague' => $colleague] = setupAgendaAvailabilityContext();

    $this->actingAs($colleague)
        ->putJson(route('clinic-settings.agendas.update', $colleague->id), [
            'agenda_visible_to_team' => true,
            'working_days' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'working_start' => '09:00',
            'working_end' => '18:00',
        ])->assertOk();
    expect($colleague->clinicPivotFor(session('current_clinic_id'))->agenda_visible_to_team)->toBeTrue();

    $this->actingAs($colleague)
        ->putJson(route('clinic-settings.agendas.update', $colleague->id), [
            'agenda_visible_to_team' => false,
            'working_days' => ['mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true, 'sun' => true],
            'working_start' => '09:00',
            'working_end' => '18:00',
        ])->assertOk();
    expect($colleague->clinicPivotFor(session('current_clinic_id'))->agenda_visible_to_team)->toBeFalse();
});

test('another user cannot view a non-shared agenda, including via a direct backend request', function () {
    ['owner' => $owner, 'colleague' => $colleague, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $colleague->clinics()->updateExistingPivot($clinic->id, ['agenda_visible_to_team' => false]);

    // Não aparece na lista de Agendas do dono.
    $this->actingAs($owner)->get(route('appointments.index'))->assertInertia(fn ($page) => $page
        ->has('professionals', 1)
        ->where('professionals.0.id', $owner->id)
    );

    // Acesso direto via professional_id na URL — bloqueado mesmo assim.
    $this->actingAs($owner)
        ->get(route('appointments.index', ['professional_id' => $colleague->id]))
        ->assertStatus(403);
});

test('a professional always keeps access to their own agenda, even with visibility off', function () {
    ['colleague' => $colleague, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $colleague->clinics()->updateExistingPivot($clinic->id, ['agenda_visible_to_team' => false]);

    $this->actingAs($colleague)
        ->get(route('appointments.index', ['professional_id' => $colleague->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.professional_id', (string) $colleague->id));

    // E continua aparecendo primeiro na própria lista de Agendas.
    $this->actingAs($colleague)->get(route('appointments.index'))->assertInertia(fn ($page) => $page
        ->where('professionals.0.id', $colleague->id)
    );
});

test('"Todos" respects each professional\'s visibility — a hidden professional\'s appointments never leak into it', function () {
    ['owner' => $owner, 'colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $colleague->clinics()->updateExistingPivot($clinic->id, ['agenda_visible_to_team' => false]);

    $monday = nextWeekday(\Carbon\Carbon::MONDAY);
    $ownerAppt = Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'treatment_id' => $treatment->id, 'start' => $monday->copy()->setTime(9, 0), 'end' => $monday->copy()->setTime(9, 30), 'status' => 'scheduled',
    ]);
    Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $colleague->id,
        'treatment_id' => $treatment->id, 'start' => $monday->copy()->setTime(10, 0), 'end' => $monday->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);

    $this->actingAs($owner)
        ->get(route('appointments.index', ['week' => $monday->toDateString()]))
        ->assertInertia(fn ($page) => $page
            ->has('appointments', 1)
            ->where('appointments.0.id', $ownerAppt->id)
        );
});

// ── Isolamento multi-tenant ──────────────────────────────────────────────────

test('a professional from another clinic never appears in the Agendas list, and cannot be filtered by id', function () {
    ['owner' => $owner, 'colleague' => $ownClinicColleague, 'clinic' => $clinic] = setupAgendaAvailabilityContext();
    ['colleague' => $foreignProfessional] = setupAgendaAvailabilityContext(); // troca a sessão pra outra clínica

    // Volta a sessão pra clínica do primeiro dono.
    session(['current_clinic_id' => $clinic->id]);

    // Só os 2 profissionais da própria clínica aparecem — nunca o da outra.
    $this->actingAs($owner)->get(route('appointments.index'))->assertInertia(fn ($page) => $page
        ->has('professionals', 2)
        ->where('professionals.0.id', $owner->id)
        ->where('professionals.1.id', $ownClinicColleague->id)
    );

    $this->actingAs($owner)
        ->get(route('appointments.index', ['professional_id' => $foreignProfessional->id]))
        ->assertStatus(403);
});

// ── Tela cheia reaproveita as mesmas regras de index() ──────────────────────
// Ganhou a sidebar de Cadeiras/Agendas nesta rodada, então precisa da mesma
// autoridade — sem isso viraria um atalho pra contornar a visibilidade.

test('fullscreen also hides a non-shared professional\'s appointments, even in "Todos"', function () {
    ['owner' => $owner, 'colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $colleague->clinics()->updateExistingPivot($clinic->id, ['agenda_visible_to_team' => false]);

    $monday = nextWeekday(\Carbon\Carbon::MONDAY);
    $ownerAppt = Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $owner->id,
        'treatment_id' => $treatment->id, 'start' => $monday->copy()->setTime(9, 0), 'end' => $monday->copy()->setTime(9, 30), 'status' => 'scheduled',
    ]);
    Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $colleague->id,
        'treatment_id' => $treatment->id, 'start' => $monday->copy()->setTime(10, 0), 'end' => $monday->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);

    $this->actingAs($owner)
        ->get(route('appointments.fullscreen', ['week' => $monday->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('appointments', 1)
            ->where('appointments.0.id', $ownerAppt->id)
            ->has('professionals', 1)
            ->where('professionals.0.id', $owner->id)
        );
});

test('fullscreen rejects a direct professional_id filter for a non-shared agenda', function () {
    ['owner' => $owner, 'colleague' => $colleague, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    $colleague->clinics()->updateExistingPivot($clinic->id, ['agenda_visible_to_team' => false]);

    $this->actingAs($owner)
        ->get(route('appointments.fullscreen', ['professional_id' => $colleague->id]))
        ->assertStatus(403);
});

test('fullscreen still respects Cadeira 01 as default and the 6-chair max, same as index()', function () {
    ['owner' => $owner, 'clinic' => $clinic] = setupAgendaAvailabilityContext();

    \App\Models\Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira 01', 'color' => '#0d9488']);
    \App\Models\Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira 02', 'color' => '#2563eb']);

    $this->actingAs($owner)
        ->get(route('appointments.fullscreen'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('chairs', 2)
            ->where('chairs.0.name', 'Cadeira 01')
            ->where('filters.chair_id', (string) \App\Models\Chair::where('name', 'Cadeira 01')->first()->id)
            ->where('maxChairs', \App\Models\Chair::MAX_PER_CLINIC)
        );
});
