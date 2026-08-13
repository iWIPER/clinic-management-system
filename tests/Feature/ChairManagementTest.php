<?php

use App\Models\Appointment;
use App\Models\Chair;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Treatment;
use App\Models\User;

function setupAgendaContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-agenda-' . uniqid(),
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
        'name' => 'Clínica Agenda',
        'slug' => 'clinica-agenda-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    $colleague = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($colleague->id, ['role' => 'staff']);

    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'João', 'sobrenome' => 'Silva', 'status' => 'ativo']);

    $treatment = Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Consulta', 'tipo' => 'procedimento',
        'duracao_padrao' => 30, 'preco_base' => 100, 'ativo' => true,
    ]);

    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic', 'colleague', 'patient', 'treatment');
}

test('a chair can be created and belongs to the current clinic', function () {
    ['user' => $user, 'clinic' => $clinic] = setupAgendaContext();

    $response = $this->actingAs($user)
        ->postJson(route('chairs.store'), ['name' => 'Cadeira 01', 'color' => '#0d9488'])
        ->assertOk()
        ->assertJsonPath('name', 'Cadeira 01')
        ->assertJsonPath('color', '#0d9488');

    $chair = Chair::findOrFail($response->json('id'));
    expect($chair->clinic_id)->toBe($clinic->id);
});

test('a chair name can be updated', function () {
    ['user' => $user, 'clinic' => $clinic] = setupAgendaContext();

    $chair = Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira 01', 'color' => '#0d9488']);

    $this->actingAs($user)
        ->putJson(route('chairs.update', $chair->id), ['name' => 'Cadeira A', 'color' => '#3b82f6'])
        ->assertOk()
        ->assertJsonPath('name', 'Cadeira A');

    expect($chair->refresh()->name)->toBe('Cadeira A');
});

test('a chair without appointments can be deleted directly', function () {
    ['user' => $user, 'clinic' => $clinic] = setupAgendaContext();

    $chair = Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira 01', 'color' => '#0d9488']);

    $this->actingAs($user)
        ->deleteJson(route('chairs.destroy', $chair->id))
        ->assertOk();

    expect(Chair::find($chair->id))->toBeNull();
});

test('deleting a chair with linked appointments requires confirmation and never deletes the appointment', function () {
    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient, 'treatment' => $treatment] = setupAgendaContext();

    $chair = Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira 01', 'color' => '#0d9488']);
    $appointment = Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $user->id,
        'treatment_id' => $treatment->id, 'chair_id' => $chair->id,
        'start' => now()->addDay(), 'end' => now()->addDay()->addMinutes(30), 'status' => 'scheduled',
    ]);

    // Sem force=true: bloqueado, nada é alterado.
    $this->actingAs($user)
        ->deleteJson(route('chairs.destroy', $chair->id))
        ->assertStatus(409)
        ->assertJsonPath('usage_count', 1);

    expect(Chair::find($chair->id))->not->toBeNull();
    expect($appointment->refresh()->chair_id)->toBe($chair->id);

    // Com force=true: exclui a cadeira, mas o agendamento continua existindo, só sem cadeira.
    $this->actingAs($user)
        ->deleteJson(route('chairs.destroy', $chair->id), ['force' => true])
        ->assertOk();

    expect(Chair::find($chair->id))->toBeNull();
    expect(Appointment::find($appointment->id))->not->toBeNull();
    expect($appointment->refresh()->chair_id)->toBeNull();
});

test('a chair cannot be managed by someone from another clinic', function () {
    ['user' => $foreignUser] = setupAgendaContext();

    $otherPlan = Plan::create([
        'name' => 'Other Plan', 'slug' => 'other-plan-agenda-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $otherClinic = Clinic::create([
        'name' => 'Outra Clínica', 'slug' => 'outra-clinica-agenda-' . uniqid(), 'type' => 'odontologia',
        'status' => 'active', 'plan_id' => $otherPlan->id,
    ]);
    $foreignChair = Chair::create(['clinic_id' => $otherClinic->id, 'name' => 'Cadeira X', 'color' => '#000']);

    $this->actingAs($foreignUser)
        ->putJson(route('chairs.update', $foreignChair->id), ['name' => 'Hackeada', 'color' => '#000'])
        ->assertStatus(403);

    $this->actingAs($foreignUser)
        ->deleteJson(route('chairs.destroy', $foreignChair->id))
        ->assertStatus(403);

    expect($foreignChair->refresh()->name)->toBe('Cadeira X');
});

test('an appointment can be created with a chair and it is returned by the agenda index', function () {
    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient, 'treatment' => $treatment] = setupAgendaContext();

    $chair = Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira 01', 'color' => '#0d9488']);
    $start = now()->addDay()->setTime(10, 0);

    $this->actingAs($user)
        ->postJson(route('appointments.store'), [
            'patient_id' => $patient->id, 'professional_id' => $user->id, 'treatment_id' => $treatment->id,
            'chair_id' => $chair->id, 'start' => $start->toDateTimeString(),
        ])
        ->assertRedirect();

    $appointment = Appointment::where('clinic_id', $clinic->id)->firstOrFail();
    expect($appointment->chair_id)->toBe($chair->id);

    $this->actingAs($user)
        ->get(route('appointments.index', ['week' => $start->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Appointments/Index')
            ->has('appointments', 1)
            ->where('appointments.0.chair.id', $chair->id)
            ->where('appointments.0.chair.name', 'Cadeira 01')
        );
});

test('an appointment cannot be created with a chair from another clinic', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupAgendaContext();

    $otherPlan = Plan::create([
        'name' => 'Other Plan 2', 'slug' => 'other-plan-agenda-2-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $otherClinic = Clinic::create([
        'name' => 'Outra Clínica 2', 'slug' => 'outra-clinica-agenda-2-' . uniqid(), 'type' => 'odontologia',
        'status' => 'active', 'plan_id' => $otherPlan->id,
    ]);
    $foreignChair = Chair::create(['clinic_id' => $otherClinic->id, 'name' => 'Cadeira Estrangeira', 'color' => '#000']);

    $this->actingAs($user)
        ->postJson(route('appointments.store'), [
            'patient_id' => $patient->id, 'professional_id' => $user->id, 'treatment_id' => $treatment->id,
            'chair_id' => $foreignChair->id, 'start' => now()->addDay()->toDateTimeString(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['chair_id']);
});

test('the agenda index filters appointments by chair, by professional, and by both combined', function () {
    ['user' => $user, 'clinic' => $clinic, 'colleague' => $colleague, 'patient' => $patient, 'treatment' => $treatment] = setupAgendaContext();

    $chairA = Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira A', 'color' => '#0d9488']);
    $chairB = Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira B', 'color' => '#3b82f6']);
    $week = now()->addDay();

    $mk = fn ($prof, $chair, $hour) => Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $prof->id,
        'treatment_id' => $treatment->id, 'chair_id' => $chair?->id,
        'start' => $week->copy()->setTime($hour, 0), 'end' => $week->copy()->setTime($hour, 30), 'status' => 'scheduled',
    ]);

    $willInChairA = $mk($user, $chairA, 9);
    $willInChairB = $mk($user, $chairB, 10);
    $colleagueInChairA = $mk($colleague, $chairA, 11);
    $unassigned = $mk($colleague, null, 12);

    // Todos ordenados por horário de início (9h, 10h, 11h, 12h) — a
    // ordenação do controller (orderBy('start')) já deixa a posição de cada
    // um previsível, sem precisar comparar conjuntos.

    // Só Cadeira A: 2 agendamentos (Will às 9h + colega às 11h), independente do profissional.
    $this->actingAs($user)
        ->get(route('appointments.index', ['week' => $week->toDateString(), 'chair_id' => $chairA->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('appointments', 2)
            ->where('appointments.0.id', $willInChairA->id)
            ->where('appointments.1.id', $colleagueInChairA->id)
        );

    // Will + Cadeira A: só o agendamento do Will na cadeira A.
    $this->actingAs($user)
        ->get(route('appointments.index', ['week' => $week->toDateString(), 'professional_id' => $user->id, 'chair_id' => $chairA->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('appointments', 1)
            ->where('appointments.0.id', $willInChairA->id)
        );

    // Will + Todas as cadeiras (explícito): os dois agendamentos do Will
    // (cadeira A às 9h e B às 10h). "Todas" agora exige o sentinel 'all' —
    // omitir chair_id não significa mais "sem filtro" (ver teste de default
    // logo abaixo).
    $this->actingAs($user)
        ->get(route('appointments.index', ['week' => $week->toDateString(), 'professional_id' => $user->id, 'chair_id' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('appointments', 2)
            ->where('appointments.0.id', $willInChairA->id)
            ->where('appointments.1.id', $willInChairB->id)
        );

    // "Todas" explícito, sem filtro de profissional: os 4, incluindo o legado sem cadeira (12h, último).
    $this->actingAs($user)
        ->get(route('appointments.index', ['week' => $week->toDateString(), 'chair_id' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('appointments', 4)
            ->where('appointments.3.id', $unassigned->id)
            ->where('appointments.3.chair', null)
        );
});

test('omitting chair_id on a fresh visit defaults to the clinic\'s first chair, not "Todas"', function () {
    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient, 'treatment' => $treatment] = setupAgendaContext();

    $chairA = Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira A', 'color' => '#0d9488']);
    $chairB = Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira B', 'color' => '#3b82f6']);
    $week = now()->addDay();

    $inChairA = Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $user->id,
        'treatment_id' => $treatment->id, 'chair_id' => $chairA->id,
        'start' => $week->copy()->setTime(9, 0), 'end' => $week->copy()->setTime(9, 30), 'status' => 'scheduled',
    ]);
    Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $user->id,
        'treatment_id' => $treatment->id, 'chair_id' => $chairB->id,
        'start' => $week->copy()->setTime(10, 0), 'end' => $week->copy()->setTime(10, 30), 'status' => 'scheduled',
    ]);

    // Nenhum chair_id na URL — deve resolver pra Cadeira A (a mais antiga)
    // e refletir isso de volta no prop `filters`, não em "Todas".
    $this->actingAs($user)
        ->get(route('appointments.index', ['week' => $week->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('appointments', 1)
            ->where('appointments.0.id', $inChairA->id)
            ->where('filters.chair_id', (string) $chairA->id)
        );

    // "Todas" explícito confirma que o comportamento de "sem filtro" ainda
    // existe — só deixou de ser o default.
    $this->actingAs($user)
        ->get(route('appointments.index', ['week' => $week->toDateString(), 'chair_id' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('appointments', 2)
            ->where('filters.chair_id', 'all')
        );
});

test('an appointment chair can be changed on update', function () {
    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient, 'treatment' => $treatment] = setupAgendaContext();

    $chairA = Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira A', 'color' => '#0d9488']);
    $chairB = Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira B', 'color' => '#3b82f6']);

    $appointment = Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $user->id,
        'treatment_id' => $treatment->id, 'chair_id' => $chairA->id,
        'start' => now()->addDay(), 'end' => now()->addDay()->addMinutes(30), 'status' => 'scheduled',
    ]);

    $this->actingAs($user)
        ->putJson(route('appointments.update', $appointment->id), [
            'patient_id' => $patient->id, 'professional_id' => $user->id, 'treatment_id' => $treatment->id,
            'chair_id' => $chairB->id, 'start' => $appointment->start->toDateTimeString(), 'status' => 'scheduled',
        ])
        ->assertRedirect();

    expect($appointment->refresh()->chair_id)->toBe($chairB->id);
});

test('a legacy appointment without a chair keeps working normally in the agenda', function () {
    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient, 'treatment' => $treatment] = setupAgendaContext();

    $week = now()->addDay();
    $legacy = Appointment::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $user->id,
        'treatment_id' => $treatment->id, 'chair_id' => null,
        'start' => $week, 'end' => $week->copy()->addMinutes(30), 'status' => 'scheduled',
    ]);

    $this->actingAs($user)
        ->get(route('appointments.index', ['week' => $week->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('appointments', 1)
            ->where('appointments.0.id', $legacy->id)
            ->where('appointments.0.chair', null)
        );

    // Editar sem informar cadeira continua funcionando (não é obrigatória).
    $this->actingAs($user)
        ->putJson(route('appointments.update', $legacy->id), [
            'patient_id' => $patient->id, 'professional_id' => $user->id, 'treatment_id' => $treatment->id,
            'start' => $legacy->start->toDateTimeString(), 'status' => 'confirmed',
        ])
        ->assertRedirect();

    expect($legacy->refresh()->status)->toBe('confirmed');
    expect($legacy->chair_id)->toBeNull();
});

test('a clinic can create up to the maximum of 6 chairs, and the 7th is rejected without touching the existing ones', function () {
    ['user' => $user, 'clinic' => $clinic] = setupAgendaContext();

    expect(Chair::MAX_PER_CLINIC)->toBe(6);

    foreach (range(1, 6) as $i) {
        $this->actingAs($user)
            ->postJson(route('chairs.store'), ['name' => sprintf('Cadeira %02d', $i), 'color' => '#0d9488'])
            ->assertOk();
    }

    expect(Chair::where('clinic_id', $clinic->id)->count())->toBe(6);

    // A 7ª é rejeitada com uma mensagem amigável, e nenhuma é criada parcialmente.
    $this->actingAs($user)
        ->postJson(route('chairs.store'), ['name' => 'Cadeira 07', 'color' => '#0d9488'])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'Sua clínica já possui o máximo de 6 cadeiras.');

    expect(Chair::where('clinic_id', $clinic->id)->count())->toBe(6);
    expect(Chair::where('clinic_id', $clinic->id)->pluck('name')->sort()->values()->all())
        ->toBe(['Cadeira 01', 'Cadeira 02', 'Cadeira 03', 'Cadeira 04', 'Cadeira 05', 'Cadeira 06']);
});

test('the chairs prop returned to the Agenda is always ordered by creation, ready for "Todas" to render last', function (int $count) {
    ['user' => $user, 'clinic' => $clinic] = setupAgendaContext();

    foreach (range(1, $count) as $i) {
        Chair::create(['clinic_id' => $clinic->id, 'name' => sprintf('Cadeira %02d', $i), 'color' => '#0d9488']);
    }

    $response = $this->actingAs($user)
        ->get(route('appointments.index'))
        ->assertOk();

    $response->assertInertia(function ($page) use ($count) {
        $page->component('Appointments/Index')->has('chairs', $count);
        foreach (range(1, $count) as $i) {
            $index = $i - 1;
            $page->where("chairs.{$index}.name", sprintf('Cadeira %02d', $i));
        }
        return $page;
    });
})->with([1, 2, 3, 6]);
