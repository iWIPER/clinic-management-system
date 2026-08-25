<?php

use App\Models\Appointment;
use App\Models\AppointmentReturn;
use App\Models\Chair;
use App\Models\Clinic;
use App\Models\ClinicalRecord;
use App\Models\Patient;
use App\Models\PatientTag;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * "Atendimentos" (rotas clinical-records.index/.show, AttendanceController)
 * passou a ser alimentado por Appointment, não por ClinicalRecord —
 * ClinicalRecord/ProcedureExecution/Treatment continuam existindo pra quem
 * realmente precisa deles (Financeiro, Pagamentos, PatientHubService,
 * ClinicalRecordController::generatePdf()), só deixaram de ser exigidos pra
 * uma consulta aparecer aqui.
 */
function setupAttendanceContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-attendance-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Atendimentos', 'slug' => 'clinica-atendimentos-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'owner']);
    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'João', 'sobrenome' => 'Silva', 'status' => 'ativo']);
    $chair = Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira 01', 'color' => '#0d9488']);

    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic', 'patient', 'chair');
}

function createAppointmentFor(array $ctx, array $overrides = []): Appointment
{
    return Appointment::create(array_merge([
        'clinic_id' => $ctx['clinic']->id,
        'patient_id' => $ctx['patient']->id,
        'professional_id' => $ctx['user']->id,
        'chair_id' => $ctx['chair']->id,
        'start' => Carbon::now()->subHour(),
        'end' => Carbon::now()->subMinutes(30),
        'status' => 'completed',
    ], $overrides));
}

test('an appointment without any clinical record appears in the attendances list', function () {
    $ctx = setupAttendanceContext();
    createAppointmentFor($ctx);

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Attendances/Index')
            ->has('appointments.data', 1)
            ->where('appointments.data.0.patient.nome', 'João')
            ->where('appointments.data.0.professional.name', $ctx['user']->name)
            ->where('appointments.data.0.chair.name', 'Cadeira 01')
            ->missing('appointments.data.0.procedure_name')
            ->missing('appointments.data.0.price')
        );
});

test('an appointment that already has a clinical record still appears only once', function () {
    $ctx = setupAttendanceContext();
    $appt = createAppointmentFor($ctx);

    ClinicalRecord::create([
        'clinic_id' => $ctx['clinic']->id, 'patient_id' => $ctx['patient']->id,
        'professional_id' => $ctx['user']->id, 'appointment_id' => $appt->id,
        'procedure_name' => 'Limpeza', 'status' => \App\Enums\ClinicalRecordStatus::Concluido,
        'finished_at' => now(), 'price' => 150,
    ]);

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('appointments.data', 1));
});

test('the attendances list has no procedure filter and no financial data', function () {
    $ctx = setupAttendanceContext();
    createAppointmentFor($ctx);

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters', fn ($filters) => ! collect($filters)->has('procedure'))
        );
});

test('the attendances list filters by patient, professional, chair, status and period', function () {
    $ctx = setupAttendanceContext();
    $match = createAppointmentFor($ctx, ['start' => Carbon::parse('2026-01-10 10:00'), 'end' => Carbon::parse('2026-01-10 10:30')]);
    createAppointmentFor($ctx, ['status' => 'cancelled', 'start' => Carbon::parse('2026-01-15 10:00'), 'end' => Carbon::parse('2026-01-15 10:30')]);

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.index', [
            'patient_id' => $ctx['patient']->id,
            'professional_id' => $ctx['user']->id,
            'chair_id' => $ctx['chair']->id,
            'status' => 'completed',
            'from' => '2026-01-01',
            'to' => '2026-01-12',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('appointments.data', 1)
            ->where('appointments.data.0.id', $match->id)
        );
});

test('attendance details show patient, professional, chair, date, time, duration and status — no procedure, no price', function () {
    $ctx = setupAttendanceContext();
    $appt = createAppointmentFor($ctx, [
        'start' => Carbon::parse('2026-02-01 14:00'),
        'end' => Carbon::parse('2026-02-01 14:45'),
    ]);

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.show', $appt->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Attendances/Show')
            ->where('appointment.patient.nome', 'João')
            ->where('appointment.professional.name', $ctx['user']->name)
            ->where('appointment.chair.name', 'Cadeira 01')
            ->where('appointment.status', 'completed')
            ->missing('appointment.procedure_name')
            ->missing('appointment.price')
        );
});

test('attendance details expose the observation only when the appointment has one', function () {
    $ctx = setupAttendanceContext();
    $withNotes = createAppointmentFor($ctx, ['notes' => 'Paciente relatou sensibilidade.']);
    $withoutNotes = createAppointmentFor($ctx, ['notes' => null]);

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.show', $withNotes->id))
        ->assertInertia(fn ($page) => $page->where('appointment.notes', 'Paciente relatou sensibilidade.'));

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.show', $withoutNotes->id))
        ->assertInertia(fn ($page) => $page->where('appointment.notes', null));
});

test('attendance details expose the return (date, reason, status) when one exists for the appointment', function () {
    $ctx = setupAttendanceContext();
    $appt = createAppointmentFor($ctx);
    AppointmentReturn::create([
        'clinic_id' => $ctx['clinic']->id, 'appointment_id' => $appt->id, 'patient_id' => $ctx['patient']->id,
        'professional_id' => $ctx['user']->id, 'due_date' => Carbon::now()->addDays(15),
        'reason' => 'Controle', 'status' => 'pending',
    ]);

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.show', $appt->id))
        ->assertInertia(fn ($page) => $page
            ->where('appointment.appointment_return.reason', 'Controle')
            ->where('appointment.appointment_return.status', 'pending')
        );
});

test('attendance details expose tags when the appointment has any', function () {
    $ctx = setupAttendanceContext();
    $appt = createAppointmentFor($ctx);
    $tag = PatientTag::create([
        'clinic_id' => $ctx['clinic']->id, 'name' => 'Avaliação', 'slug' => 'avaliacao-attendance',
        'color' => '#ef4444', 'is_patient_marker' => true,
    ]);
    $appt->tags()->sync([$tag->id]);

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.show', $appt->id))
        ->assertInertia(fn ($page) => $page
            ->has('appointment.tags', 1)
            ->where('appointment.tags.0.name', 'Avaliação')
        );
});

test('the attendances list paginates with the same shape and default per_page as patients', function () {
    $ctx = setupAttendanceContext();
    for ($i = 0; $i < 12; $i++) {
        createAppointmentFor($ctx, ['start' => Carbon::now()->subDays($i)->subHour(), 'end' => Carbon::now()->subDays($i)]);
    }

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('appointments.data', 10)
            ->where('appointments.pagination.current_page', 1)
            ->where('appointments.pagination.last_page', 2)
            ->where('appointments.pagination.total', 12)
            ->where('appointments.pagination.per_page', 10)
            ->where('filters.per_page', 10)
        );
});

test('the attendances list honours an explicit per_page and page number', function () {
    $ctx = setupAttendanceContext();
    for ($i = 0; $i < 22; $i++) {
        createAppointmentFor($ctx, ['start' => Carbon::now()->subDays($i)->subHour(), 'end' => Carbon::now()->subDays($i)]);
    }

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.index', ['per_page' => 10, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('appointments.data', 10)
            ->where('appointments.pagination.current_page', 2)
            ->where('appointments.pagination.last_page', 3)
            ->where('appointments.pagination.total', 22)
        );
});

test('the attendances list falls back to per_page=10 when given a per_page outside the allowed options', function () {
    $ctx = setupAttendanceContext();
    createAppointmentFor($ctx);

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.index', ['per_page' => 999]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('appointments.pagination.per_page', 10)
            ->where('filters.per_page', 10)
        );
});

test('a filtered attendances list with few records reports last_page=1 without breaking pagination', function () {
    $ctx = setupAttendanceContext();
    createAppointmentFor($ctx);

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('appointments.data', 1)
            ->where('appointments.pagination.last_page', 1)
            ->where('appointments.pagination.current_page', 1)
        );
});

test('an attendances list with zero results still returns a valid empty pagination shape', function () {
    $ctx = setupAttendanceContext();

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.index', ['status' => 'no_show']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('appointments.data', 0)
            ->where('appointments.pagination.total', 0)
            ->where('appointments.pagination.last_page', 1)
        );
});

test('exporting attendances streams a csv without procedure or price columns', function () {
    $ctx = setupAttendanceContext();
    createAppointmentFor($ctx);

    $response = $this->actingAs($ctx['user'])
        ->get(route('clinical-records.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    expect($csv)->toContain('Data', 'Paciente', 'Profissional', 'Cadeira', 'Status')
        ->and($csv)->not->toContain('Procedimento')
        ->and($csv)->not->toContain('Valor')
        ->and($csv)->not->toContain('Preço');
});

test('exporting attendances respects the currently active filters', function () {
    $ctx = setupAttendanceContext();
    $otherPatient = Patient::create(['clinic_id' => $ctx['clinic']->id, 'nome' => 'Maria', 'sobrenome' => 'Souza', 'status' => 'ativo']);

    createAppointmentFor($ctx);
    createAppointmentFor(array_merge($ctx, ['patient' => $otherPatient]), ['patient_id' => $otherPatient->id]);

    $response = $this->actingAs($ctx['user'])
        ->get(route('clinical-records.export', ['patient_id' => $ctx['patient']->id]))
        ->assertOk();

    $csv = $response->streamedContent();
    expect($csv)->toContain('João Silva')
        ->and($csv)->not->toContain('Maria Souza');
});

test('exporting attendances with filters that match nothing returns only the header row', function () {
    $ctx = setupAttendanceContext();
    createAppointmentFor($ctx);

    $response = $this->actingAs($ctx['user'])
        ->get(route('clinical-records.export', ['status' => 'no_show']))
        ->assertOk();

    $lines = array_values(array_filter(explode("\n", $response->streamedContent())));
    expect($lines)->toHaveCount(1)
        ->and($lines[0])->toContain('Data');
});

test('clinical record generatePdf keeps working independently of the attendances screen', function () {
    \Illuminate\Support\Facades\Storage::fake('s3');
    $ctx = setupAttendanceContext();
    $appt = createAppointmentFor($ctx);
    $record = ClinicalRecord::create([
        'clinic_id' => $ctx['clinic']->id, 'patient_id' => $ctx['patient']->id,
        'professional_id' => $ctx['user']->id, 'appointment_id' => $appt->id,
        'procedure_name' => 'Limpeza', 'status' => \App\Enums\ClinicalRecordStatus::Concluido,
        'finished_at' => now(), 'price' => 150,
    ]);

    $this->actingAs($ctx['user'])
        ->get(route('clinical-records.pdf', $record))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
