<?php

use App\Models\PatientOdontogram;
use App\Models\ClinicalEvolution;
use App\Models\User;

/**
 * Reescrito após a remoção completa do antigo módulo de Prontuário
 * (PatientProntuarioController, PatientAnamnesis, página Prontuario/Show.vue
 * — arquitetura aprovada). Duas das cinco coberturas originais não têm mais
 * equivalente (a página em si e a geração de PDF do prontuário eram
 * exclusivas do módulo removido) — as outras duas testavam funcionalidades
 * que continuam existindo, só que através de rotas/controllers diferentes,
 * já ativos antes desta mudança: PatientOdontogramController::update() (pra
 * onde updateOdontogram() foi realocado) e PatientEvolutionController::store()
 * (que já era o caminho real de criação de evolução usado pela Visão Geral —
 * a rota antiga era um caminho paralelo/legado que nunca era chamado dali).
 */
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

test('can register clinical evolution via the patient page (PatientEvolutionController)', function () {
    ['user' => $user, 'patient' => $patient] = setupProntuarioContext();

    $this->actingAs($user)
        ->post(route('patients.evolutions.store', $patient), [
            'professional_id' => $user->id,
            'recorded_at' => now()->toDateString(),
            'content' => "Paciente sem dor.\nRealizada limpeza.\nOrientações fornecidas.",
        ])
        ->assertRedirect();

    expect(ClinicalEvolution::where('patient_id', $patient->id)->count())->toBe(1);
});

test('can save odontogram via PatientOdontogramController::update (realocado do antigo Prontuário)', function () {
    ['user' => $user, 'patient' => $patient] = setupProntuarioContext();

    $teethData = PatientOdontogram::defaultTeethData();
    $teethData['36']['status'] = 'cariado';

    $this->actingAs($user)
        ->put(route('patients.odontogram.update', $patient), [
            'teeth_data' => $teethData,
            'notes' => 'Cárie no 36',
        ])
        ->assertRedirect();

    $odontogram = PatientOdontogram::where('patient_id', $patient->id)->first();
    expect($odontogram->teeth_data['36']['status'])->toBe('cariado');
});

test('the old prontuario routes no longer exist', function () {
    ['user' => $user, 'patient' => $patient] = setupProntuarioContext();

    expect(\Illuminate\Support\Facades\Route::has('patients.prontuario'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Route::has('patients.prontuario.anamnesis'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Route::has('patients.prontuario.evolutions'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Route::has('patients.prontuario.odontogram'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Route::has('patients.prontuario.pdf'))->toBeFalse();
});
