<?php

use App\Models\AnamnesisInstance;
use App\Models\AnamnesisQuestion;
use App\Models\AnamnesisTemplate;
use App\Models\AnamnesisTemplateQuestion;
use App\Models\Clinic;
use App\Models\Plan;
use App\Models\User;
use App\Services\PatientInviteService;

// PNG mínimo válido (1x1, transparente) — mesmo usado para simular a
// assinatura capturada por AnamnesisSignaturePad.vue.
const FAKE_SIGNATURE_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

function setupAnamnesisWizardContext(array $inviteOverrides = []): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-anamnesis-' . uniqid(),
        'is_free' => true, 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);

    $clinic = Clinic::create([
        'name' => 'Clínica Anamnese', 'slug' => 'clinica-anamnese-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);

    $reception = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Secretario']);
    $dentist = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista']);
    $clinic->users()->attach($reception->id, ['role' => 'owner']);
    $clinic->users()->attach($dentist->id, ['role' => 'member']);

    $template = AnamnesisTemplate::create([
        'clinic_id' => $clinic->id, 'name' => 'Adulta Teste', 'slug' => 'adulta-teste-' . uniqid(),
        'version' => 1, 'is_system' => false, 'is_active' => true, 'is_default' => false, 'sort_order' => 1,
        'created_by_id' => $reception->id,
    ]);

    $requiredQuestion = AnamnesisQuestion::create([
        'clinic_id' => $clinic->id, 'category' => 'GERAL', 'text' => 'Possui alergia?',
        'type' => 'yes_no', 'is_required' => true, 'has_alert' => false, 'is_active' => true,
        'question_hash' => hash('sha256', 'possui-alergia-' . uniqid()),
    ]);
    $optionalQuestion = AnamnesisQuestion::create([
        'clinic_id' => $clinic->id, 'category' => 'GERAL', 'text' => 'Toma algum medicamento?',
        'type' => 'yes_no', 'is_required' => false, 'has_alert' => false, 'is_active' => true,
        'question_hash' => hash('sha256', 'toma-medicamento-' . uniqid()),
    ]);
    AnamnesisTemplateQuestion::create(['template_id' => $template->id, 'question_id' => $requiredQuestion->id, 'sort_order' => 1, 'is_required' => true]);
    AnamnesisTemplateQuestion::create(['template_id' => $template->id, 'question_id' => $optionalQuestion->id, 'sort_order' => 2, 'is_required' => false]);

    $invite = app(PatientInviteService::class)->create(array_merge([
        'nome' => 'Paciente', 'sobrenome' => 'Anamnese', 'telefone' => '11987654321',
        'kind' => 'cadastro', 'channel' => 'link_only', 'expires_in_days' => 7,
        'allow_anamnesis' => true, 'anamnesis_template_id' => $template->id,
    ], $inviteOverrides), $clinic->id, $reception->id);

    return compact('clinic', 'reception', 'dentist', 'template', 'requiredQuestion', 'optionalQuestion', 'invite');
}

// opened_at precisa estar setado — no fluxo real, o convite já foi aberto
// muito antes de chegar em aguardando_conclusao; sem isso,
// markOpenedIfNeeded() (chamado por show()) reverteria o status para
// 'visualizado' na próxima requisição, como se fosse a primeira abertura.
function completeBaseRegistrationSteps($invite): void
{
    $invite->update(['status' => 'aguardando_conclusao', 'progress' => 100, 'opened_at' => now()]);
}

test('complete() transitions to aguardando_conclusao when allow_anamnesis is true, not to concluido', function () {
    ['invite' => $invite] = setupAnamnesisWizardContext();

    $result = app(PatientInviteService::class)->complete($invite);
    $invite->refresh();

    expect($invite->status)->toBe('aguardando_conclusao');
    expect($invite->completed_at)->toBeNull();
    expect($result['status'])->toBe('aguardando_conclusao');
    expect($result['anamnese']['categories'])->toHaveCount(1);
});

test('complete() still goes straight to concluido when allow_anamnesis is false (no regression from Fases 1-3)', function () {
    ['clinic' => $clinic, 'reception' => $reception] = setupAnamnesisWizardContext();

    $invite = app(PatientInviteService::class)->create([
        'nome' => 'Outro', 'sobrenome' => 'Paciente', 'telefone' => '11900000099',
        'kind' => 'cadastro', 'channel' => 'link_only', 'expires_in_days' => 7,
    ], $clinic->id, $reception->id);

    $result = app(PatientInviteService::class)->complete($invite);
    $invite->refresh();

    expect($invite->status)->toBe('concluido');
    expect($invite->completed_at)->not->toBeNull();
    expect($result['status'])->toBe('concluido');
});

test('resolveAnamnesisProfessionalId uses the patient responsible professional when set', function () {
    ['invite' => $invite, 'dentist' => $dentist] = setupAnamnesisWizardContext();
    $invite->patient->update(['responsible_professional_id' => $dentist->id]);
    $invite->refresh();

    $resolved = app(PatientInviteService::class)->resolveAnamnesisProfessionalId($invite);

    expect($resolved)->toBe($dentist->id);
});

test('resolveAnamnesisProfessionalId falls back to the invite creator when patient has no responsible professional', function () {
    ['invite' => $invite, 'reception' => $reception] = setupAnamnesisWizardContext();

    $resolved = app(PatientInviteService::class)->resolveAnamnesisProfessionalId($invite);

    expect($resolved)->toBe($reception->id);
});

test('findOrCreateAnamnesisInstance is idempotent and logs anamnesis_started only once', function () {
    ['invite' => $invite] = setupAnamnesisWizardContext();
    $service = app(PatientInviteService::class);

    $first = $service->findOrCreateAnamnesisInstance($invite);
    $invite->refresh();
    $second = $service->findOrCreateAnamnesisInstance($invite);

    expect($invite->anamnesis_instance_id)->toBe($first->id);
    expect($second->id)->toBe($first->id);
    expect($invite->activityLogs()->where('action', 'anamnesis_started')->count())->toBe(1);
});

test('show renders the anamnese step with existing questions when invite is aguardando_conclusao', function () {
    ['invite' => $invite] = setupAnamnesisWizardContext();
    completeBaseRegistrationSteps($invite);

    $response = test()->get(route('patient-invites.public.show', $invite->token));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('PatientInvites/PublicWizard')
        ->has('anamnese.categories.0.questions', 2)
        ->where('conclusion', null)
    );
});

test('resuming aguardando_conclusao returns previously saved answers, not a fresh form', function () {
    ['invite' => $invite, 'requiredQuestion' => $q1] = setupAnamnesisWizardContext();
    completeBaseRegistrationSteps($invite);

    test()->patchJson(route('patient-invites.public.anamnese.update', $invite->token), [
        'answers' => [['question_id' => $q1->id, 'value' => 'nao']],
    ])->assertOk();

    $response = test()->get(route('patient-invites.public.show', $invite->token));

    $response->assertInertia(fn ($page) => $page
        ->where('anamnese.categories.0.questions.0.value', 'nao')
    );

    // Reabrir de novo continua devolvendo a mesma instância (não duplica).
    expect(AnamnesisInstance::where('patient_id', $invite->patient_id)->count())->toBe(1);
});

test('updateAnamnesis rejects requests on an invite that is not awaiting anamnesis conclusion', function () {
    ['invite' => $invite, 'requiredQuestion' => $q1] = setupAnamnesisWizardContext();
    // invite.status ainda é 'gerado', não 'aguardando_conclusao'.

    $response = test()->patchJson(route('patient-invites.public.anamnese.update', $invite->token), [
        'answers' => [['question_id' => $q1->id, 'value' => 'nao']],
    ]);

    $response->assertStatus(410);
});

test('completeAnamnesis rejects signature when a required question is unanswered', function () {
    ['invite' => $invite, 'optionalQuestion' => $q2] = setupAnamnesisWizardContext();
    completeBaseRegistrationSteps($invite);

    // Só a opcional respondida — a obrigatória (requiredQuestion) fica sem valor.
    test()->patchJson(route('patient-invites.public.anamnese.update', $invite->token), [
        'answers' => [['question_id' => $q2->id, 'value' => 'sim']],
    ])->assertOk();

    $response = test()->postJson(route('patient-invites.public.anamnese.complete', $invite->token), [
        'signature_data' => FAKE_SIGNATURE_PNG,
        'patient_name'   => 'Paciente Anamnese',
    ]);

    $response->assertStatus(422);
    $invite->refresh();
    expect($invite->status)->toBe('aguardando_conclusao');
    expect($invite->anamnesisInstance->isSigned())->toBeFalse();
});

test('completeAnamnesis signs and completes the invite once all required questions are answered', function () {
    ['invite' => $invite, 'requiredQuestion' => $q1, 'optionalQuestion' => $q2] = setupAnamnesisWizardContext();
    completeBaseRegistrationSteps($invite);

    test()->patchJson(route('patient-invites.public.anamnese.update', $invite->token), [
        'answers' => [
            ['question_id' => $q1->id, 'value' => 'nao'],
            ['question_id' => $q2->id, 'value' => 'sim'],
        ],
    ])->assertOk();

    $response = test()->postJson(route('patient-invites.public.anamnese.complete', $invite->token), [
        'signature_data' => FAKE_SIGNATURE_PNG,
        'patient_name'   => 'Paciente Anamnese',
        'patient_cpf'    => '123.456.789-00',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'concluido');

    $invite->refresh();
    expect($invite->status)->toBe('concluido');
    expect($invite->completed_at)->not->toBeNull();
    expect($invite->anamnesis_completed_at)->not->toBeNull();
    expect($invite->anamnesisInstance->fresh()->isSigned())->toBeTrue();

    $logs = $invite->activityLogs()->orderBy('created_at')->orderBy('id')->pluck('action')->toArray();
    expect(array_slice($logs, -3))->toBe(['anamnesis_started', 'anamnesis_completed', 'completed']);
});

test('completeAnamnesis rejects an already-signed instance (double submit)', function () {
    ['invite' => $invite, 'requiredQuestion' => $q1] = setupAnamnesisWizardContext();
    completeBaseRegistrationSteps($invite);

    test()->patchJson(route('patient-invites.public.anamnese.update', $invite->token), [
        'answers' => [['question_id' => $q1->id, 'value' => 'nao']],
    ])->assertOk();

    test()->postJson(route('patient-invites.public.anamnese.complete', $invite->token), [
        'signature_data' => FAKE_SIGNATURE_PNG,
        'patient_name'   => 'Paciente Anamnese',
    ])->assertOk();

    // Segunda tentativa (ex.: duplo clique / duas abas): convite já concluído,
    // rejeitado pela guarda de status antes mesmo de olhar a assinatura.
    $response = test()->postJson(route('patient-invites.public.anamnese.complete', $invite->token), [
        'signature_data' => FAKE_SIGNATURE_PNG,
        'patient_name'   => 'Paciente Anamnese',
    ]);

    $response->assertStatus(410);
});

test('anamnese endpoints reject a nonexistent token the same way as the base wizard endpoints', function () {
    test()->patchJson(route('patient-invites.public.anamnese.update', 'token-invalido'), ['answers' => []])
        ->assertStatus(410);

    test()->postJson(route('patient-invites.public.anamnese.complete', 'token-invalido'), [
        'signature_data' => FAKE_SIGNATURE_PNG,
        'patient_name'   => 'X',
    ])->assertStatus(410);
});
