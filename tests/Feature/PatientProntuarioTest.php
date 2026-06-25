<?php

use App\Models\ClinicalEvolution;
use App\Models\PatientAnamnesis;
use App\Models\PatientOdontogram;
use App\Models\User;

function setupProntuarioContext(): array
{
    $plan = \App\Models\Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-pront',
        'is_free' => true,
        'price_monthly_cents' => 0,
        'price_yearly_cents' => 0,
        'max_clinics' => 1,
        'max_patients' => 100,
        'max_users' => 5,
        'storage_gb' => 1,
        'features' => [],
    ]);

    $clinic = \App\Models\Clinic::create([
        'name' => 'Clínica Teste',
        'slug' => 'clinica-pront-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
        'trade_name' => 'LELIS CARE',
        'slogan' => 'Cuidando de você',
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    $patient = \App\Models\Patient::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Maria',
        'sobrenome' => 'Santos',
        'status' => 'ativo',
    ]);

    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic', 'patient');
}

test('prontuario page is accessible', function () {
    ['user' => $user, 'patient' => $patient] = setupProntuarioContext();

    $this->actingAs($user)
        ->get(route('patients.prontuario', $patient))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Prontuario/Show')
            ->has('patient')
            ->has('anamnesis')
            ->has('odontogram')
        );
});

test('can save anamnesis', function () {
    ['user' => $user, 'patient' => $patient] = setupProntuarioContext();

    $this->actingAs($user)
        ->put(route('patients.prontuario.anamnesis', $patient), [
            'queixa_principal' => 'Dor no dente 36',
            'alergias' => 'Penicilina',
            'hipertensao' => true,
            'gestante' => false,
            'diabetes' => false,
            'cardiopatia' => false,
            'hemorragia' => false,
            'fumo' => false,
            'alcool' => false,
        ])
        ->assertRedirect();

    $anamnesis = PatientAnamnesis::where('patient_id', $patient->id)->first();
    expect($anamnesis)->not->toBeNull()
        ->and($anamnesis->queixa_principal)->toBe('Dor no dente 36')
        ->and($anamnesis->hipertensao)->toBeTrue();
});

test('can register clinical evolution', function () {
    ['user' => $user, 'patient' => $patient] = setupProntuarioContext();

    $this->actingAs($user)
        ->post(route('patients.prontuario.evolutions', $patient), [
            'content' => "Paciente sem dor.\nRealizada limpeza.\nOrientações fornecidas.",
        ])
        ->assertRedirect();

    expect(ClinicalEvolution::where('patient_id', $patient->id)->count())->toBe(1);
});

test('can save odontogram', function () {
    ['user' => $user, 'patient' => $patient] = setupProntuarioContext();

    $teethData = PatientOdontogram::defaultTeethData();
    $teethData['36']['status'] = 'cariado';

    $this->actingAs($user)
        ->put(route('patients.prontuario.odontogram', $patient), [
            'teeth_data' => $teethData,
            'notes' => 'Cárie no 36',
        ])
        ->assertRedirect();

    $odontogram = PatientOdontogram::where('patient_id', $patient->id)->first();
    expect($odontogram->teeth_data['36']['status'])->toBe('cariado');
});

test('generates prontuario pdf', function () {
    ['user' => $user, 'patient' => $patient] = setupProntuarioContext();

    PatientAnamnesis::create([
        'clinic_id' => $patient->clinic_id,
        'patient_id' => $patient->id,
        'queixa_principal' => 'Avaliação inicial',
    ]);

    $this->actingAs($user)
        ->get(route('patients.prontuario.pdf', $patient))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});