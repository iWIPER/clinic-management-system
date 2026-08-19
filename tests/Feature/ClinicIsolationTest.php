<?php

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\User;

// Auditoria de segurança — falha crítica de isolamento multi-tenant:
// ClinicScope deixava de filtrar (fail-open) quando session('current_clinic_id')
// estava ausente, e EnsureCurrentClinic deixava a requisição prosseguir mesmo
// assim. Um usuário autenticado sem clínica (ex.: recém-cadastrado via
// /register, antes de criar/entrar em uma clínica) conseguia ver dados de
// TODAS as clínicas. Corrigido em ClinicScope::apply() (fail-closed) e
// EnsureCurrentClinic (bloqueia + revalida current_clinic_id contra
// clinic_user real). Estes testes cobrem a camada de middleware+scope
// diretamente, não só Patient — a correção é arquitetural.

function setupClinicIsolationContext(string $suffix, string $role = 'owner'): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-isolation' . $suffix . '-' . uniqid(),
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
        'name' => 'Clínica Isolamento' . $suffix,
        'slug' => 'clinica-isolamento' . $suffix . '-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => $role]);

    return compact('plan', 'clinic', 'user');
}

// A) usuário autenticado sem clínica → pacientes negado
test('a user with no clinic at all cannot access the patients list', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('patients.index'))
        ->assertRedirect(route('onboarding.choose-role'));
});

// B) usuário autenticado sem clínica → exportação de pacientes negada
test('a user with no clinic at all cannot export patients', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('patients.export'))
        ->assertRedirect(route('onboarding.choose-role'));
});

// C) Clínica A não acessa dados da Clínica B
test('a user from clinic A cannot see a patient belonging to clinic B', function () {
    ['clinic' => $clinicA, 'user' => $userA] = setupClinicIsolationContext('-c-a');
    ['clinic' => $clinicB] = setupClinicIsolationContext('-c-b');
    $patientB = Patient::create(['clinic_id' => $clinicB->id, 'nome' => 'PacienteB', 'sobrenome' => 'X', 'status' => 'ativo']);

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->get(route('patients.show', $patientB))
        ->assertStatus(404);
});

// D) Clínica A acessa dados da Clínica A normalmente
test('a user from clinic A can see a patient belonging to clinic A', function () {
    ['clinic' => $clinicA, 'user' => $userA] = setupClinicIsolationContext('-d');
    $patientA = Patient::create(['clinic_id' => $clinicA->id, 'nome' => 'PacienteA', 'sobrenome' => 'X', 'status' => 'ativo']);

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->get(route('patients.show', $patientA))
        ->assertOk();
});

// E) usuário com duas clínicas — cada contexto ativo só devolve seus dados
test('a user belonging to two clinics only sees the active clinic patient when switching context', function () {
    ['clinic' => $clinicA, 'user' => $user] = setupClinicIsolationContext('-e-a');
    ['clinic' => $clinicB] = setupClinicIsolationContext('-e-b');
    $clinicB->users()->attach($user->id, ['role' => 'owner']);

    $patientA = Patient::create(['clinic_id' => $clinicA->id, 'nome' => 'PacienteA', 'sobrenome' => 'X', 'status' => 'ativo']);
    $patientB = Patient::create(['clinic_id' => $clinicB->id, 'nome' => 'PacienteB', 'sobrenome' => 'X', 'status' => 'ativo']);

    $this->actingAs($user)->withSession(['current_clinic_id' => $clinicA->id])
        ->get(route('patients.show', $patientA))->assertOk();

    $this->actingAs($user)->withSession(['current_clinic_id' => $clinicA->id])
        ->get(route('patients.show', $patientB))->assertStatus(404);

    $this->actingAs($user)->withSession(['current_clinic_id' => $clinicB->id])
        ->get(route('patients.show', $patientB))->assertOk();

    $this->actingAs($user)->withSession(['current_clinic_id' => $clinicB->id])
        ->get(route('patients.show', $patientA))->assertStatus(404);
});

// F) current_clinic_id manipulado para uma clínica alheia → nunca confiado
test('a tampered current_clinic_id pointing to a clinic the user does not belong to is never trusted', function () {
    ['clinic' => $clinicA, 'user' => $userA] = setupClinicIsolationContext('-f-a');
    ['clinic' => $clinicB] = setupClinicIsolationContext('-f-b');
    $patientB = Patient::create(['clinic_id' => $clinicB->id, 'nome' => 'PacienteB', 'sobrenome' => 'X', 'status' => 'ativo']);

    // userA pertence só à clinicA, mas a sessão é manipulada pra apontar
    // pra clinicB — o middleware deve descartar o valor, reconfirmar contra
    // clinic_user e cair de volta pra clínica real do usuário.
    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicB->id])
        ->get(route('patients.show', $patientB))
        ->assertStatus(404);
});

test('a tampered current_clinic_id for a user with no real clinic at all is rejected outright', function () {
    ['clinic' => $clinicB] = setupClinicIsolationContext('-f-c');
    $userNoClinic = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($userNoClinic)->withSession(['current_clinic_id' => $clinicB->id])
        ->get(route('patients.index'))
        ->assertRedirect(route('onboarding.choose-role'));
});

// G) onboarding de novo usuário continua funcionando
test('a freshly registered user with no clinic can still complete onboarding end to end', function () {
    $this->post(route('register'), [
        'name' => 'Novo Usuário',
        'email' => 'novo-onboarding@example.com',
        'password' => 'senha12345',
        'password_confirmation' => 'senha12345',
    ])->assertRedirect(route('onboarding.choose-role'));

    $user = User::where('email', 'novo-onboarding@example.com')->firstOrFail();

    $this->actingAs($user)
        ->get(route('onboarding.choose-role'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('onboarding.create-clinic'))
        ->assertOk();

    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-onboarding-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);

    $this->actingAs($user)
        ->post(route('onboarding.create-clinic'), [
            'name' => 'Minha Nova Clínica',
            'type' => 'odontologia',
            'cnpj' => '',
            'plan_slug' => $plan->slug,
            'onboarding_stage' => 'new',
            'onboarding_current_system' => 'paper_or_calendar',
            'chairs_count' => 1,
        ])
        ->assertRedirect(route('onboarding.complete'));

    $clinic = Clinic::where('name', 'Minha Nova Clínica')->firstOrFail();
    expect($clinic->users()->where('users.id', $user->id)->wherePivot('role', 'owner')->exists())->toBeTrue();

    // Onboarding concluído — agora tem clínica válida e acessa normalmente.
    $this->actingAs($user)
        ->get(route('patients.index'))
        ->assertOk();
});
