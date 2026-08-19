<?php

use App\Models\AnamnesisInstance;
use App\Models\AnamnesisQuestion;
use App\Models\AnamnesisTemplate;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Convenio;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\Patient;
use App\Models\PatientTag;
use App\Models\PatientTreatment;
use App\Models\Plan;
use App\Models\Task;
use App\Models\Treatment;
use App\Models\Transaction;
use App\Models\User;

// Auditoria de segurança (2ª etapa) — vários exists:/Rule::exists validavam
// só a existência do ID na tabela inteira, sem checar clinic_id. Cada teste
// aqui reproduz o exemplo mínimo do pedido: Clínica A possui um recurso,
// Clínica B tenta referenciá-lo pelo ID — resultado esperado é sempre
// rejeição (422/404), nunca associação cross-tenant.

function setupTwoClinicsForCrossTenantAudit(): array
{
    $makeClinic = function (string $suffix) {
        $plan = Plan::create([
            'name' => 'Test Plan', 'slug' => 'test-plan-xtenant' . $suffix . '-' . uniqid(), 'is_free' => true,
            'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
            'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
        ]);
        $clinic = Clinic::create([
            'name' => 'Clínica XTenant' . $suffix, 'slug' => 'clinica-xtenant' . $suffix . '-' . uniqid(),
            'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
        ]);
        $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
        $clinic->users()->attach($user->id, ['role' => 'owner']);
        $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Paciente' . $suffix, 'sobrenome' => 'X', 'status' => 'ativo']);

        return compact('clinic', 'user', 'patient');
    };

    ['clinic' => $clinicA, 'user' => $userA, 'patient' => $patientA] = $makeClinic('-a');
    ['clinic' => $clinicB, 'user' => $userB, 'patient' => $patientB] = $makeClinic('-b');

    return compact('clinicA', 'userA', 'patientA', 'clinicB', 'userB', 'patientB');
}

test('an appointment cannot be created with a professional from another clinic', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'patientA' => $patientA, 'userB' => $userB] = setupTwoClinicsForCrossTenantAudit();

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->postJson(route('appointments.store'), [
            'patient_id' => $patientA->id,
            'professional_id' => $userB->id,
            'start' => now()->addDay()->setTime(10, 0)->toIso8601String(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['professional_id']);

    expect(Appointment::where('patient_id', $patientA->id)->exists())->toBeFalse();
});

test('an appointment cannot be reassigned to a professional from another clinic on update', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'patientA' => $patientA, 'userB' => $userB] = setupTwoClinicsForCrossTenantAudit();

    $appointment = Appointment::create([
        'clinic_id' => $clinicA->id, 'patient_id' => $patientA->id, 'professional_id' => $userA->id,
        'start' => now()->addDay()->setTime(10, 0), 'end' => now()->addDay()->setTime(10, 30), 'status' => 'scheduled',
    ]);

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->putJson(route('appointments.update', $appointment), [
            'patient_id' => $patientA->id,
            'professional_id' => $userB->id,
            'start' => $appointment->start->toIso8601String(),
            'status' => 'scheduled',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['professional_id']);

    expect($appointment->fresh()->professional_id)->toBe($userA->id);
});

test('an evolution cannot be attributed to a professional from another clinic', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'patientA' => $patientA, 'userB' => $userB] = setupTwoClinicsForCrossTenantAudit();

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->postJson(route('patients.evolutions.store', $patientA), [
            'professional_id' => $userB->id,
            'recorded_at' => now()->toDateString(),
            'content' => 'Evolução de teste',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['professional_id']);
});

test('a patient treatment cannot be created with a professional from another clinic', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'patientA' => $patientA, 'userB' => $userB] = setupTwoClinicsForCrossTenantAudit();

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->postJson(route('patients.treatments.store', $patientA), [
            'procedure_name' => 'Procedimento teste',
            'professional_id' => $userB->id,
            'treatment_date' => now()->toDateString(),
            'value_charged' => 100,
            'cost' => 50,
            'status' => 'planejado',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['professional_id']);

    expect(PatientTreatment::where('patient_id', $patientA->id)->exists())->toBeFalse();
});

test('a patient treatment cannot use a convenio from another clinic', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'patientA' => $patientA, 'clinicB' => $clinicB] = setupTwoClinicsForCrossTenantAudit();
    $convenioB = Convenio::create(['clinic_id' => $clinicB->id, 'nome' => 'Convênio B', 'ativo' => true]);

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->postJson(route('patients.treatments.store', $patientA), [
            'procedure_name' => 'Procedimento teste',
            'professional_id' => $userA->id,
            'convenio_id' => $convenioB->id,
            'treatment_date' => now()->toDateString(),
            'value_charged' => 100,
            'cost' => 50,
            'status' => 'planejado',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['convenio_id']);
});

test('finalizing a treatment cannot attribute it to a professional from another clinic', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'patientA' => $patientA, 'userB' => $userB] = setupTwoClinicsForCrossTenantAudit();

    $treatment = PatientTreatment::create([
        'clinic_id' => $clinicA->id, 'patient_id' => $patientA->id, 'procedure_name' => 'Procedimento',
        'professional_id' => $userA->id, 'treatment_date' => now(), 'value_charged' => 100, 'cost' => 50,
        'status' => 'planejado', 'budget_code' => 'TESTE-1',
    ]);

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->postJson(route('patients.treatments.finalize', [$patientA, $treatment]), [
            'professional_id' => $userB->id,
            'completed_at' => now()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['professional_id']);

    expect($treatment->fresh()->status)->toBe('planejado');
});

test('a document cannot be issued from a private template belonging to another clinic', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'patientA' => $patientA, 'clinicB' => $clinicB] = setupTwoClinicsForCrossTenantAudit();

    $categoryB = DocumentCategory::create(['clinic_id' => $clinicB->id, 'name' => 'Categoria B', 'slug' => 'categoria-b-' . uniqid(), 'is_system' => false, 'is_active' => true]);
    $templateB = DocumentTemplate::create(['clinic_id' => $clinicB->id, 'category_id' => $categoryB->id, 'name' => 'Modelo Privado B', 'slug' => 'modelo-b-' . uniqid(), 'is_system' => false, 'is_active' => true]);
    $templateB->createNewVersion('Modelo Privado B', '<p>Conteúdo confidencial da Clínica B</p>', 'Criação', 1);

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->post(route('patients.documents.store', $patientA), [
            'template_id' => $templateB->id,
        ])
        ->assertSessionHasErrors(['template_id']);

    expect(Document::where('patient_id', $patientA->id)->exists())->toBeFalse();
});

test('a document template cannot be assigned to a private category from another clinic', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'clinicB' => $clinicB] = setupTwoClinicsForCrossTenantAudit();
    $categoryB = DocumentCategory::create(['clinic_id' => $clinicB->id, 'name' => 'Categoria B', 'slug' => 'categoria-b2-' . uniqid(), 'is_system' => false, 'is_active' => true]);

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->postJson(route('document-templates.store'), [
            'category_id' => $categoryB->id,
            'name' => 'Modelo A',
            'content_html' => '<p>conteudo</p>',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['category_id']);

    expect(DocumentTemplate::where('clinic_id', $clinicA->id)->exists())->toBeFalse();
});

test('a newly created question cannot be attached to a private template from another clinic', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'clinicB' => $clinicB] = setupTwoClinicsForCrossTenantAudit();
    $templateB = AnamnesisTemplate::create(['clinic_id' => $clinicB->id, 'name' => 'Modelo Privado B', 'slug' => 'modelo-privado-b-' . uniqid(), 'is_system' => false, 'is_active' => true]);

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->postJson(route('anamnesis-questions.store'), [
            'text' => 'Pergunta nova',
            'category' => 'GERAL',
            'type' => 'yes_no',
            'template_id' => $templateB->id,
        ])
        ->assertStatus(404);

    expect($templateB->fresh()->templateQuestions()->count())->toBe(0);
});

test('an anamnesis template cannot attach a private question from another clinic', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'clinicB' => $clinicB] = setupTwoClinicsForCrossTenantAudit();
    $template = AnamnesisTemplate::create(['clinic_id' => $clinicA->id, 'name' => 'Modelo A', 'slug' => 'modelo-anam-a-' . uniqid(), 'is_system' => false, 'is_active' => true]);
    $questionB = AnamnesisQuestion::create([
        'clinic_id' => $clinicB->id, 'category' => 'GERAL', 'text' => 'Pergunta privada da Clínica B',
        'type' => 'yes_no', 'is_required' => false, 'is_active' => true,
        'question_hash' => hash('sha256', 'pergunta-privada-b-' . uniqid()),
    ]);

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->postJson(route('anamnesis-templates.questions.attach', $template), [
            'question_id' => $questionB->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['question_id']);

    expect($template->templateQuestions()->count())->toBe(0);
});

test('saving anamnesis answers never copies a private question text from another clinic into this instance', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'patientA' => $patientA, 'clinicB' => $clinicB] = setupTwoClinicsForCrossTenantAudit();

    $questionB = AnamnesisQuestion::create([
        'clinic_id' => $clinicB->id, 'category' => 'GERAL', 'text' => 'Pergunta privada da Clínica B',
        'type' => 'yes_no', 'is_required' => false, 'is_active' => true,
        'question_hash' => hash('sha256', 'pergunta-privada-b2-' . uniqid()),
    ]);

    $template = AnamnesisTemplate::create(['clinic_id' => $clinicA->id, 'name' => 'Modelo A', 'slug' => 'modelo-anam-a2-' . uniqid(), 'is_system' => false, 'is_active' => true]);

    $instance = AnamnesisInstance::create([
        'clinic_id' => $clinicA->id, 'patient_id' => $patientA->id, 'template_id' => $template->id,
        'template_name' => $template->name, 'template_version' => 1, 'professional_id' => $userA->id,
        'status' => 'rascunho', 'progress' => 0, 'started_at' => now(), 'anamnesis_date' => now(),
        'validation_token' => bin2hex(random_bytes(16)),
    ]);

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->putJson(route('patients.anamneses.answers', [$patientA, $instance]), [
            'answers' => [
                ['question_id' => $questionB->id, 'value' => 'sim'],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['answers.0.question_id']);

    expect($instance->answers()->count())->toBe(0);
});

test('a finance transaction cannot be attributed to a patient from another clinic', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'patientB' => $patientB] = setupTwoClinicsForCrossTenantAudit();

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->postJson(route('finance.store-transaction'), [
            'tipo' => 'receita', 'valor' => 100, 'categoria' => 'consulta',
            'patient_id' => $patientB->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['patient_id']);

    expect(Transaction::where('clinic_id', $clinicA->id)->exists())->toBeFalse();
});

test('a budget cannot be created for a patient from another clinic', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'patientB' => $patientB] = setupTwoClinicsForCrossTenantAudit();

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->postJson(route('finance.create-budget'), [
            'patient_id' => $patientB->id,
            'total' => 500,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['patient_id']);
});

test('a treatment cannot be created with a parent group from another clinic', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'clinicB' => $clinicB] = setupTwoClinicsForCrossTenantAudit();
    $parentB = Treatment::create(['clinic_id' => $clinicB->id, 'nome' => 'Grupo B', 'tipo' => 'grupo', 'ativo' => true]);

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->postJson(route('treatments.store'), [
            'nome' => 'Procedimento A',
            'tipo' => 'variacao',
            'parent_id' => $parentB->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['parent_id']);
});

test('a task cannot be assigned to a user from another clinic', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'userB' => $userB] = setupTwoClinicsForCrossTenantAudit();

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->postJson(route('tasks.store'), [
            'title' => 'Tarefa qualquer',
            'description' => 'Descrição qualquer',
            'status' => 'todo',
            'priority' => 'media',
            'assigned_to' => $userB->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['assigned_to']);

    expect(Task::where('assigned_to', $userB->id)->exists())->toBeFalse();
});

test('a private custom marker from another clinic cannot be attached to a note, appointment or patient', function () {
    ['clinicA' => $clinicA, 'userA' => $userA, 'patientA' => $patientA, 'clinicB' => $clinicB] = setupTwoClinicsForCrossTenantAudit();
    $markerB = PatientTag::create(['clinic_id' => $clinicB->id, 'name' => 'Marcador B', 'slug' => 'marcador-b-' . uniqid(), 'is_system' => false, 'is_patient_marker' => true]);

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->putJson(route('patients.markers.sync', $patientA), [
            'marker_ids' => [$markerB->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['marker_ids.0']);

    expect($patientA->fresh()->markers()->count())->toBe(0);

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->postJson(route('patients.notes.store', $patientA), [
            'title' => 'Nota', 'tag_ids' => [$markerB->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tag_ids.0']);
});
