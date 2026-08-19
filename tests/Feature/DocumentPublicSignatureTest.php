<?php

use App\Enums\Documents\DocumentStatus;
use App\Models\Clinic;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentSignature;
use App\Models\DocumentTemplate;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\User;

/**
 * Fase A, item 5 — fluxo público (sem autenticação) de assinatura remota de
 * documento por token: Public\DocumentPublicSignatureController.
 */
function setupPublicSignatureContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-pubsig-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);

    $clinic = Clinic::create([
        'name' => 'Clínica Assinatura', 'slug' => 'clinica-pubsig-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Paciente', 'sobrenome' => 'Assinatura', 'status' => 'ativo']);

    $category = DocumentCategory::create([
        'clinic_id' => $clinic->id, 'name' => 'Consentimentos', 'slug' => 'consentimentos-' . uniqid(),
        'is_system' => false, 'is_active' => true,
    ]);

    $template = DocumentTemplate::create([
        'clinic_id' => $clinic->id, 'category_id' => $category->id,
        'name' => 'Consentimento', 'slug' => 'consentimento-' . uniqid(),
        'requires_patient_signature' => true, 'requires_professional_signature' => false,
        'is_system' => false, 'created_by_id' => $user->id,
    ]);
    $template->createNewVersion('Consentimento', '<p>Eu autorizo o procedimento.</p>', 'Criação', $user->id);

    return compact('plan', 'clinic', 'user', 'patient', 'category', 'template');
}

function createSignableDocument(array $ctx, ?string $expiresAt = null): Document
{
    $document = Document::create([
        'clinic_id' => $ctx['clinic']->id,
        'patient_id' => $ctx['patient']->id,
        'template_id' => $ctx['template']->id,
        'template_version_id' => $ctx['template']->current_version_id,
        'template_name' => $ctx['template']->name,
        'professional_id' => $ctx['user']->id,
        'status' => DocumentStatus::Issued->value,
        'rendered_html' => '<p>Eu autorizo o procedimento.</p>',
        'document_code' => 'DOC-SIGN-' . uniqid(),
        'created_by_id' => $ctx['user']->id,
        'signature_token' => bin2hex(random_bytes(32)),
        'signature_token_expires_at' => $expiresAt ?? now()->addHours(72),
    ]);

    return $document->fresh();
}

function fakePngBase64(): string
{
    return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
}

test('a valid signature token shows the document for public signing', function () {
    $ctx = setupPublicSignatureContext();
    $document = createSignableDocument($ctx);

    $this->get(route('documents.public-sign', $document->signature_token))
        ->assertOk()
        ->assertSee($ctx['template']->name, false);
});

test('an invalid or nonexistent signature token does not reveal the document', function () {
    $this->get(route('documents.public-sign', 'token-que-nao-existe'))
        ->assertOk()
        ->assertViewHas('valid', false);
});

test('an expired signature token is rejected and marks the document as expired', function () {
    $ctx = setupPublicSignatureContext();
    $document = createSignableDocument($ctx, now()->subHour()->toDateTimeString());

    $this->get(route('documents.public-sign', $document->signature_token))
        ->assertOk()
        ->assertViewHas('valid', false);

    expect($document->fresh())
        ->status->toBe(DocumentStatus::Expired->value)
        ->signature_token->toBeNull();
});

test('signing with an allowed public role and valid signature data completes the document', function () {
    $ctx = setupPublicSignatureContext();
    $document = createSignableDocument($ctx);

    $response = $this->post(route('documents.public-sign.store', $document->signature_token), [
        'signer_role' => 'patient',
        'signature_data' => fakePngBase64(),
        'signer_name' => 'Paciente Assinatura',
    ])->assertOk();

    expect($response->json('completed'))->toBeTrue();

    expect(DocumentSignature::where('document_id', $document->id)->where('signer_role', 'patient')->exists())->toBeTrue();
    expect($document->fresh()->status)->toBe(DocumentStatus::Completed->value);
});

test('signing with a role not permitted on the public link is rejected', function () {
    $ctx = setupPublicSignatureContext();
    $document = createSignableDocument($ctx);

    // 'professional' nunca pode assinar pelo link público (ver
    // DocumentPublicSignatureController::PUBLIC_ROLES) — exige sessão autenticada.
    $this->post(route('documents.public-sign.store', $document->signature_token), [
        'signer_role' => 'professional',
        'signature_data' => fakePngBase64(),
        'signer_name' => 'Tentativa Indevida',
    ])->assertStatus(422);

    expect(DocumentSignature::where('document_id', $document->id)->exists())->toBeFalse();
});

test('signing with an invalid or expired token is rejected with 410 and creates no signature', function () {
    $this->post(route('documents.public-sign.store', 'token-invalido'), [
        'signer_role' => 'patient',
        'signature_data' => fakePngBase64(),
        'signer_name' => 'Ninguém',
    ])->assertStatus(410);

    expect(DocumentSignature::count())->toBe(0);
});

test('a cancelled document cannot be signed via its old public link', function () {
    $ctx = setupPublicSignatureContext();
    $document = createSignableDocument($ctx);
    $document->update(['status' => DocumentStatus::Cancelled->value]);

    $this->get(route('documents.public-sign', $document->signature_token))
        ->assertOk()
        ->assertViewHas('valid', false);

    $this->post(route('documents.public-sign.store', $document->signature_token), [
        'signer_role' => 'patient',
        'signature_data' => fakePngBase64(),
        'signer_name' => 'Paciente',
    ])->assertStatus(410);
});
