<?php

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\ProcedureExecution;
use App\Models\Treatment;
use App\Models\User;
use App\Services\PatientHubService;
use Illuminate\Support\Facades\DB;

/**
 * Fase B4.3 — regressão da poda de PatientHubService::ensureRelations().
 * 'appointments.consultation.procedureExecutions.treatment' e as três
 * variantes de 'consultations.*' nunca eram lidas por nenhum método da
 * classe (só formatAppointment() usa ->treatment/->professional de um
 * Appointment; os outros usos de $patient->consultations são count()/
 * where()/contains() sobre a coleção base, sem relações aninhadas).
 * Medido: 8 das 9 queries desse bloco eram descartadas — 38 → 30 queries
 * no load completo da ficha do paciente neste cenário.
 */
function setupHubEnsureRelationsContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-hub-er-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica ER', 'slug' => 'clinica-hub-er-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $dentist = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($dentist->id, ['role' => 'owner']);
    $treatment = Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Consulta', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 30, 'preco_base' => 100, 'custo_padrao' => 40,
    ]);

    session(['current_clinic_id' => $clinic->id]);

    $patient = Patient::create([
        'clinic_id' => $clinic->id, 'nome' => 'Paciente', 'sobrenome' => 'ER', 'status' => 'ativo',
    ]);

    foreach (range(1, 3) as $i) {
        $appointment = Appointment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $dentist->id,
            'start' => now()->subDays($i)->subHour(), 'end' => now()->subDays($i),
            'status' => 'completed', 'treatment_id' => $treatment->id,
        ]);
        $consultation = Consultation::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $dentist->id,
            'appointment_id' => $appointment->id, 'status' => 'finalizado',
            'started_at' => $appointment->start, 'finished_at' => $appointment->end,
        ]);
        ProcedureExecution::create([
            'clinic_id' => $clinic->id, 'consultation_id' => $consultation->id, 'treatment_id' => $treatment->id,
            'executed_at' => $appointment->end, 'price_charged' => 100,
        ]);
    }

    return compact('dentist', 'clinic', 'patient');
}

test('the appointments/consultations block of ensureRelations() stays lean (regression against the dead nested eager loads)', function () {
    ['dentist' => $dentist, 'patient' => $patient] = setupHubEnsureRelationsContext();

    $queries = [];
    DB::listen(function ($q) use (&$queries) { $queries[] = $q->sql; });

    $this->actingAs($dentist)->get(route('patients.show', $patient))->assertOk();

    $consultationsBlock = array_values(array_filter($queries, fn ($sql) =>
        str_contains($sql, 'from "consultations"')
        || str_contains($sql, 'from "procedure_executions"')
        || (str_contains($sql, 'from "appointments"') && str_contains($sql, 'in ('))
        || (str_contains($sql, 'from "treatments"') && str_contains($sql, '"id" in'))
    ));

    // Antes da B4: 12 queries neste bloco (3 delas descartadas nunca lidas
    // pela classe). Depois: 4 (appointments + treatment + professional +
    // consultations base). Damos folga (6) para não quebrar por mudanças
    // incidentais, mas nunca deve voltar a crescer proporcionalmente às
    // 3 relações aninhadas mortas.
    expect(count($consultationsBlock))->toBeLessThanOrEqual(6);
});

test('consultationsGrouped, clinicalSummary and relationshipSummary keep correct values after trimming dead relations', function () {
    ['patient' => $patient] = setupHubEnsureRelationsContext();

    $hub = app(PatientHubService::class)->build(Patient::find($patient->id));

    expect($hub['summary']['clinical']['consultations_completed'])->toBe(3)
        ->and($hub['consultations']['completed'])->toHaveCount(3)
        ->and($hub['consultations']['completed'][0]['treatment'])->toBe('Consulta')
        ->and($hub['consultations']['completed'][0]['professional'])->not->toBeNull()
        ->and($hub['summary']['relationship']['attendances'])->toBe(3);
});
