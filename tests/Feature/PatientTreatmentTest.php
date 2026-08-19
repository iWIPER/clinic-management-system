<?php

use App\Models\Clinic;
use App\Models\ClinicalEvolution;
use App\Models\Convenio;
use App\Models\Patient;
use App\Models\PatientTreatment;
use App\Models\PatientTreatmentAuditLog;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\Treatment;
use App\Models\User;

function setupPatientTreatmentContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-pt-' . uniqid(),
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
        'name' => 'Clínica Tratamentos',
        'slug' => 'clinica-pt-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    session(['current_clinic_id' => $clinic->id]);

    $patient = Patient::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Paciente',
        'sobrenome' => 'Teste',
        'status' => 'ativo',
    ]);

    $convenio = Convenio::create(['clinic_id' => $clinic->id, 'nome' => 'Particular', 'ativo' => true]);

    $treatment = Treatment::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Consulta Inicial',
        'tipo' => 'procedimento',
        'ativo' => true,
        'duracao_padrao' => 30,
        'preco_base' => 80,
        'custo_padrao' => 80,
    ]);

    return compact('user', 'clinic', 'patient', 'convenio', 'treatment');
}

test('patient show page with treatments tab exposes patient treatment data', function () {
    ['user' => $user, 'patient' => $patient] = setupPatientTreatmentContext();

    $this->actingAs($user)
        ->get(route('patients.show', $patient) . '?tab=treatments')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Patients/Show')
            ->where('activeTab', 'treatments')
            ->has('patientTreatments')
            ->has('catalogTreatments')
            ->has('convenios')
            ->has('treatmentStatuses')
            ->has('treatmentsByTooth')
        );
});

test('can add a treatment with an elegant budget code', function () {
    ['user' => $user, 'patient' => $patient, 'convenio' => $convenio, 'treatment' => $treatment] = setupPatientTreatmentContext();

    $this->actingAs($user)
        ->post(route('patients.treatments.store', $patient), [
            'treatment_id' => $treatment->id,
            'professional_id' => $user->id,
            'convenio_id' => $convenio->id,
            'treatment_date' => now()->toDateString(),
            'tooth' => '11',
            'faces' => ['M', 'O'],
            'value_charged' => 80,
            'cost' => 80,
            'status' => 'futuro',
            'notes' => 'Observação de teste',
        ])
        ->assertRedirect();

    $pt = PatientTreatment::where('patient_id', $patient->id)->first();

    expect($pt)->not->toBeNull()
        ->and($pt->procedure_name)->toBe('Consulta Inicial')
        ->and($pt->tooth)->toBe('11')
        ->and($pt->faces)->toBe(['M', 'O'])
        ->and($pt->status)->toBe('futuro')
        ->and($pt->budget_code)->toMatch('/^TRT-\d{6}-\d{4}$/');

    expect(PatientTreatmentAuditLog::where('patient_treatment_id', $pt->id)->where('action', 'created')->exists())->toBeTrue();
});

test('cannot create a treatment already as concluido — must use finalize flow', function () {
    ['user' => $user, 'patient' => $patient] = setupPatientTreatmentContext();

    $this->actingAs($user)
        ->post(route('patients.treatments.store', $patient), [
            'procedure_name' => 'Livre',
            'treatment_date' => now()->toDateString(),
            'value_charged' => 50,
            'cost' => 20,
            'status' => 'concluido',
        ])
        ->assertSessionHasErrors('status');
});

test('finalize marks treatment as concluido, logs evolution and creates a financial transaction', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientTreatmentContext();

    $pt = PatientTreatment::create([
        'clinic_id' => $patient->clinic_id,
        'patient_id' => $patient->id,
        'treatment_id' => $treatment->id,
        'procedure_name' => $treatment->nome,
        'budget_code' => PatientTreatment::nextBudgetCode($patient->clinic_id, now()),
        'value_charged' => 80,
        'cost' => 80,
        'status' => 'futuro',
        'treatment_date' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->post(route('patients.treatments.finalize', [$patient, $pt]), [
            'professional_id' => $user->id,
            'completed_at' => now()->toDateString(),
            'evolution' => '<p>Procedimento realizado com sucesso.</p>',
            'update_stock' => false,
        ])
        ->assertRedirect();

    $pt->refresh();

    expect($pt->status)->toBe('concluido')
        ->and($pt->completed_at)->not->toBeNull();

    expect(ClinicalEvolution::where('patient_treatment_id', $pt->id)->exists())->toBeTrue();
    expect(Transaction::where('origem_type', PatientTreatment::class)->where('origem_id', $pt->id)->exists())->toBeTrue();
});

test('finalized treatments cannot be edited, cost-updated or deleted', function () {
    ['user' => $user, 'patient' => $patient] = setupPatientTreatmentContext();

    $pt = PatientTreatment::create([
        'clinic_id' => $patient->clinic_id,
        'patient_id' => $patient->id,
        'procedure_name' => 'Concluído',
        'budget_code' => PatientTreatment::nextBudgetCode($patient->clinic_id, now()),
        'value_charged' => 100,
        'cost' => 50,
        'status' => 'concluido',
        'treatment_date' => now()->toDateString(),
        'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->delete(route('patients.treatments.destroy', [$patient, $pt]))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(PatientTreatment::find($pt->id))->not->toBeNull();

    $this->actingAs($user)
        ->post(route('patients.treatments.cost', [$patient, $pt]), ['value_charged' => 200, 'cost' => 60])
        ->assertSessionHas('error');

    $pt->refresh();
    expect((float) $pt->value_charged)->toBe(100.0);
});

test('updating cost with save_as_default updates the catalog treatment', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientTreatmentContext();

    $pt = PatientTreatment::create([
        'clinic_id' => $patient->clinic_id,
        'patient_id' => $patient->id,
        'treatment_id' => $treatment->id,
        'procedure_name' => $treatment->nome,
        'budget_code' => PatientTreatment::nextBudgetCode($patient->clinic_id, now()),
        'value_charged' => 80,
        'cost' => 80,
        'status' => 'futuro',
        'treatment_date' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->post(route('patients.treatments.cost', [$patient, $pt]), [
            'value_charged' => 120,
            'cost' => 60,
            'save_as_default' => true,
        ])
        ->assertRedirect();

    // save_as_default só grava o Custo como padrão do catálogo — Valor
    // (preco_base) é o preço sugerido, editável livremente por fora, e nunca
    // sobrescrito por essa checkbox (ver PatientTreatmentController::updateCost
    // e o label do checkbox em UpdateCostModal.vue). preco_base deve
    // permanecer no valor original de cadastro (80), não no value_charged (120)
    // que acabou de ser cobrado para este paciente específico.
    $treatment->refresh();
    expect((float) $treatment->preco_base)->toBe(80.0)
        ->and((float) $treatment->custo_padrao)->toBe(60.0);

    expect(PatientTreatmentAuditLog::where('patient_treatment_id', $pt->id)->where('action', 'cost_changed')->exists())->toBeTrue();
});

test('updating cost with save_as_default never overwrites the catalog price (preco_base)', function () {
    ['user' => $user, 'patient' => $patient, 'treatment' => $treatment] = setupPatientTreatmentContext();

    $pt = PatientTreatment::create([
        'clinic_id' => $patient->clinic_id,
        'patient_id' => $patient->id,
        'treatment_id' => $treatment->id,
        'procedure_name' => $treatment->nome,
        'budget_code' => PatientTreatment::nextBudgetCode($patient->clinic_id, now()),
        'value_charged' => 80,
        'cost' => 80,
        'status' => 'futuro',
        'treatment_date' => now()->toDateString(),
    ]);

    $originalPrecoBase = (float) $treatment->preco_base;

    $this->actingAs($user)
        ->post(route('patients.treatments.cost', [$patient, $pt]), [
            'value_charged' => 999,
            'cost' => 500,
            'save_as_default' => true,
        ])
        ->assertRedirect();

    expect((float) $treatment->refresh()->preco_base)->toBe($originalPrecoBase);
});

test('duplicating a finalized treatment creates a new futuro entry', function () {
    ['user' => $user, 'patient' => $patient] = setupPatientTreatmentContext();

    $pt = PatientTreatment::create([
        'clinic_id' => $patient->clinic_id,
        'patient_id' => $patient->id,
        'procedure_name' => 'Original',
        'tooth' => '21',
        'budget_code' => PatientTreatment::nextBudgetCode($patient->clinic_id, now()),
        'value_charged' => 90,
        'cost' => 40,
        'status' => 'concluido',
        'treatment_date' => now()->toDateString(),
        'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('patients.treatments.duplicate', [$patient, $pt]))
        ->assertRedirect();

    $copy = PatientTreatment::where('patient_id', $patient->id)->where('id', '!=', $pt->id)->first();

    expect($copy)->not->toBeNull()
        ->and($copy->status)->toBe('futuro')
        ->and($copy->procedure_name)->toBe('Original')
        ->and($copy->tooth)->toBe('21')
        ->and($copy->budget_code)->not->toBe($pt->budget_code);
});
