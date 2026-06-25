<?php

use App\Enums\ClinicalRecordStatus;
use App\Models\Appointment;
use App\Models\ClinicalEvolution;
use App\Models\Clinic;
use App\Models\ClinicalRecord;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Treatment;
use App\Models\User;
use App\Services\ClinicalRecordService;
use Illuminate\Support\Carbon;

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
        'status' => 'in_attendance',
    ]);

    $consultation = Consultation::create([
        'clinic_id' => $clinic->id,
        'patient_id' => $patient->id,
        'professional_id' => $user->id,
        'appointment_id' => $appointment->id,
        'status' => 'em_atendimento',
        'check_in_at' => Carbon::now()->subMinutes(45),
        'started_at' => Carbon::now()->subMinutes(30),
        'notes' => 'Paciente colaborativo.',
    ]);

    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic', 'patient', 'treatment', 'appointment', 'consultation');
}

test('creates clinical record when consultation is finished', function () {
    ['user' => $user, 'consultation' => $consultation, 'treatment' => $treatment] = setupClinicalRecordContext();

    $this->actingAs($user)
        ->post(route('consultations.finish', $consultation), ['notes' => 'Finalizado com sucesso'])
        ->assertRedirect();

    $record = ClinicalRecord::first();

    expect($record)->not->toBeNull()
        ->and($record->procedure_name)->toBe('Limpeza')
        ->and($record->procedure_category)->toBe('Preventiva')
        ->and($record->status)->toBe(ClinicalRecordStatus::Concluido)
        ->and((float) $record->price)->toBe(150.0)
        ->and($record->notes)->toBe('Finalizado com sucesso')
        ->and($record->consultation_id)->toBe($consultation->id);

    $consultation->refresh();
    expect($consultation->status)->toBe('finalizado');

    expect(ClinicalEvolution::where('consultation_id', $consultation->id)->count())->toBe(1);
});

test('clinical record persists when appointment is deleted', function () {
    ['appointment' => $appointment, 'consultation' => $consultation] = setupClinicalRecordContext();

    $record = app(ClinicalRecordService::class)->createFromConsultation($consultation);

    $appointment->delete();

    $record->refresh();
    expect($record->appointment_id)->toBeNull()
        ->and(ClinicalRecord::count())->toBe(1)
        ->and($record->procedure_name)->toBe('Limpeza');
});

test('clinical record is not duplicated on repeated finish', function () {
    ['consultation' => $consultation] = setupClinicalRecordContext();

    $service = app(ClinicalRecordService::class);
    $first = $service->createFromConsultation($consultation);
    $second = $service->createFromConsultation($consultation);

    expect($first->id)->toBe($second->id)
        ->and(ClinicalRecord::count())->toBe(1);
});

test('generates pdf for clinical record', function () {
    ['user' => $user, 'clinic' => $clinic, 'consultation' => $consultation] = setupClinicalRecordContext();

    $clinic->update(['trade_name' => 'Sorriso Perfeito', 'slogan' => 'Excelência em odontologia']);

    $record = app(ClinicalRecordService::class)->createFromConsultation($consultation);

    $this->actingAs($user)
        ->get(route('clinical-records.pdf', $record))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $record->refresh();
    expect($record->pdf_path)->not->toBeNull();
});

test('clinical records index is accessible with filters', function () {
    ['user' => $user, 'patient' => $patient, 'consultation' => $consultation] = setupClinicalRecordContext();

    app(ClinicalRecordService::class)->createFromConsultation($consultation);

    $this->actingAs($user)
        ->get(route('clinical-records.index', ['patient_id' => $patient->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ClinicalRecords/Index')
            ->has('records.data', 1)
        );
});