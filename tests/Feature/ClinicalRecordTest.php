<?php

use App\Enums\ClinicalRecordStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicalRecord;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Reescrito duas vezes: primeiro pra arquitetura pós-remoção de Consultas
 * (ClinicalRecord passou a apontar pra appointment_id, não mais
 * consultation_id); agora pra separação Atendimentos/ClinicalRecord —
 * ClinicalRecordController perdeu index()/show() (viraram AttendanceController,
 * Appointment-based, ver AttendanceTest.php) e ficou só com generatePdf(),
 * usado por quem realmente precisa de ClinicalRecord (Financeiro/Pagamentos/
 * PatientHubService). Aqui só testamos o que ainda é responsabilidade real
 * deste model/controller: a FK appointment_id (nullOnDelete) e o PDF.
 */
function setupClinicalRecordContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan',
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
        'name' => 'Clínica Teste',
        'slug' => 'clinica-teste',
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $clinic->users()->attach($user->id, ['role' => 'owner']);

    $patient = Patient::create([
        'clinic_id' => $clinic->id,
        'nome' => 'João',
        'sobrenome' => 'Silva',
        'status' => 'ativo',
    ]);

    $treatment = Treatment::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Limpeza',
        'especialidade' => 'Preventiva',
        'duracao_padrao' => 30,
        'preco_base' => 150.00,
        'ativo' => true,
    ]);

    $appointment = Appointment::create([
        'clinic_id' => $clinic->id,
        'patient_id' => $patient->id,
        'professional_id' => $user->id,
        'treatment_id' => $treatment->id,
        'start' => Carbon::now()->subHour(),
        'end' => Carbon::now(),
        'status' => 'completed',
    ]);

    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic', 'patient', 'treatment', 'appointment');
}

function createClinicalRecordFor(array $ctx, array $overrides = []): ClinicalRecord
{
    return ClinicalRecord::create(array_merge([
        'clinic_id' => $ctx['clinic']->id,
        'patient_id' => $ctx['patient']->id,
        'professional_id' => $ctx['user']->id,
        'appointment_id' => $ctx['appointment']->id,
        'procedure_name' => 'Limpeza',
        'procedure_category' => 'Preventiva',
        'status' => ClinicalRecordStatus::Concluido,
        'started_at' => Carbon::now()->subMinutes(30),
        'finished_at' => Carbon::now(),
        'duration_minutes' => 30,
        'price' => 150.00,
        'notes' => 'Finalizado com sucesso',
    ], $overrides));
}

test('clinical record links to its appointment, not a consultation', function () {
    $ctx = setupClinicalRecordContext();

    $record = createClinicalRecordFor($ctx);

    expect($record->appointment_id)->toBe($ctx['appointment']->id)
        ->and($record->appointment->id)->toBe($ctx['appointment']->id);
});

test('clinical record survives when its appointment is deleted (nullOnDelete)', function () {
    $ctx = setupClinicalRecordContext();
    $record = createClinicalRecordFor($ctx);

    $ctx['appointment']->delete();

    $record->refresh();
    expect($record->appointment_id)->toBeNull()
        ->and(ClinicalRecord::count())->toBe(1)
        ->and($record->procedure_name)->toBe('Limpeza');
});

test('generates pdf for clinical record', function () {
    // Fase A.3: PDF agora é gravado no disco 's3' (privado) — fake evita
    // que o teste tente alcançar a AWS real.
    \Illuminate\Support\Facades\Storage::fake('s3');
    $ctx = setupClinicalRecordContext();
    $ctx['clinic']->update(['trade_name' => 'Sorriso Perfeito', 'slogan' => 'Excelência em odontologia']);

    $record = createClinicalRecordFor($ctx);

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.pdf', $record))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $record->refresh();
    expect($record->pdf_path)->not->toBeNull();
});
