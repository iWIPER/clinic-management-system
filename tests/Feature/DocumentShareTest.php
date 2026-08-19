<?php

use App\Mail\DocumentShareMail;
use App\Mail\DocumentSharePasswordMail;
use App\Models\Clinic;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentShare;
use App\Models\DocumentShareLog;
use App\Models\DocumentTemplate;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Fase A.3/A.4 — compartilhamento seguro de documento: senha aleatória,
 * PDF protegido, verificação de identidade (nome parcial + CPF), auditoria.
 */
function setupDocumentShareContext(string $suffix = ''): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-share' . $suffix . '-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);

    $clinic = Clinic::create([
        'name' => 'Clínica Compartilhamento' . $suffix,
        'slug' => 'clinica-share' . $suffix . '-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    $patient = Patient::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Maria Luiza',
        'sobrenome' => 'da Costa Silverio Rocha' . $suffix,
        'status' => 'ativo',
        'cpf' => '529.982.247-25',
        'email' => 'maria' . $suffix . '@example.com',
    ]);

    $category = DocumentCategory::create([
        'clinic_id' => $clinic->id, 'name' => 'Anamnese', 'slug' => 'anamnese-share' . $suffix . '-' . uniqid(),
        'is_system' => false, 'is_active' => true,
    ]);

    $template = DocumentTemplate::create([
        'clinic_id' => $clinic->id, 'category_id' => $category->id,
        'name' => 'Anamnese Odontológica', 'slug' => 'anamnese-odonto' . $suffix . '-' . uniqid(),
        'requires_patient_signature' => false, 'is_system' => false, 'created_by_id' => $user->id,
    ]);
    $template->createNewVersion('Anamnese Odontológica', '<p>Conteúdo</p>', 'Criação', $user->id);

    session(['current_clinic_id' => $clinic->id]);

    return compact('plan', 'clinic', 'user', 'patient', 'category', 'template');
}

function createShareableDocument(array $ctx): Document
{
    $document = Document::create([
        'clinic_id' => $ctx['clinic']->id,
        'patient_id' => $ctx['patient']->id,
        'template_id' => $ctx['template']->id,
        'template_version_id' => $ctx['template']->current_version_id,
        'template_name' => $ctx['template']->name,
        'professional_id' => $ctx['user']->id,
        'status' => 'completed',
        'rendered_html' => '<p>Conteúdo do documento</p>',
        'document_code' => 'DOC-SHARE-' . uniqid(),
        'created_by_id' => $ctx['user']->id,
    ]);

    $path = 'documents/document-' . $document->id . '.pdf';
    Storage::disk('s3')->put($path, '%PDF-1.4 fake content');
    $document->update(['pdf_path' => $path]);

    return $document->fresh();
}

// ─────────────────────────────────────────────────────────────────────────
// Iniciar compartilhamento (lado autenticado da clínica)
// ─────────────────────────────────────────────────────────────────────────

test('1. an authorized clinic user can share a document', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);

    $this->actingAs($ctx['user'])
        ->post(route('patients.documents.share', [$ctx['patient'], $document]), [
            'recipient_email' => 'destinatario@example.com',
            'recipient_name'  => 'Maria',
        ])
        ->assertRedirect();

    $share = DocumentShare::where('shareable_id', $document->id)->where('shareable_type', Document::class)->first();

    expect($share)->not->toBeNull()
        ->and($share->recipient_email)->toBe('destinatario@example.com')
        ->and($share->clinic_id)->toBe($ctx['clinic']->id)
        ->and($share->patient_id)->toBe($ctx['patient']->id)
        ->and($share->status)->toBe(DocumentShare::STATUS_PENDING)
        ->and(Storage::disk('s3')->exists($share->storage_path))->toBeTrue();
});

test('2. a user from another clinic cannot share a document that is not theirs', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);

    ['user' => $foreignUser, 'clinic' => $foreignClinic] = setupDocumentShareContext('-foreign');
    session(['current_clinic_id' => $foreignClinic->id]);

    $this->actingAs($foreignUser)
        ->post(route('patients.documents.share', [$ctx['patient'], $document]), [
            'recipient_email' => 'atacante@example.com',
        ])
        ->assertStatus(404);

    expect(DocumentShare::count())->toBe(0);
});

test('12/13. sharing a document that does not belong to the given patient is rejected', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);

    $otherPatient = Patient::create(['clinic_id' => $ctx['clinic']->id, 'nome' => 'Outro', 'sobrenome' => 'Paciente', 'status' => 'ativo']);

    $this->actingAs($ctx['user'])
        ->post(route('patients.documents.share', [$otherPatient, $document]), [
            'recipient_email' => 'x@example.com',
        ])
        ->assertStatus(404);

    expect(DocumentShare::count())->toBe(0);
});

test('3/4. sharing sends the initial email to the right address with the protected pdf attached when small enough', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);

    $this->actingAs($ctx['user'])
        ->post(route('patients.documents.share', [$ctx['patient'], $document]), [
            'recipient_email' => 'destinatario@example.com',
        ])
        ->assertRedirect();

    // Fase B5: o envio em si agora acontece dentro de
    // GenerateAndSendDocumentShareJob (que share() despacha), não mais
    // inline na request — o job já é a unidade assíncrona, então o e-mail
    // é enviado direto (->send()) de dentro dele, sem reenfileirar por cima.
    Mail::assertSent(DocumentShareMail::class, function ($mail) {
        return $mail->hasTo('destinatario@example.com')
            && $mail->attachmentBytes !== null;
    });
});

// ─────────────────────────────────────────────────────────────────────────
// Token público — 5/6/7. válido / expirado / inválido
// ─────────────────────────────────────────────────────────────────────────

test('5. a valid, unexpired token shows the identity verification form', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', null, $ctx['user']->id);

    $this->get(route('documents.shared.password.show', $share->token))
        ->assertOk()
        ->assertViewHas('valid', true)
        ->assertViewHas('verified', false);
});

test('6. an expired token is rejected', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', null, $ctx['user']->id);
    $share->update(['expires_at' => now()->subDay()]);

    $this->get(route('documents.shared.password.show', $share->token))
        ->assertOk()
        ->assertViewHas('valid', false);
});

test('7. a nonexistent token is rejected', function () {
    $this->get(route('documents.shared.password.show', 'token-que-nao-existe'))
        ->assertOk()
        ->assertViewHas('valid', false);
});

// ─────────────────────────────────────────────────────────────────────────
// Identidade — 8/9/10/11
// ─────────────────────────────────────────────────────────────────────────

test('8/14. correct name and cpf reveal the password', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', null, $ctx['user']->id);

    $this->post(route('documents.shared.password.verify', $share->token), [
            'name' => 'Maria Luiza',
            'cpf'  => '529.982.247-25',
        ])
        ->assertRedirect(route('documents.shared.password.show', $share->token));

    $response = $this->get(route('documents.shared.password.show', $share->token));
    $response->assertOk()->assertViewHas('verified', true);
    expect($response->getContent())->toContain($share->fresh()->password_encrypted);
});

test('9. a valid partial name is accepted per the matching rule', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', null, $ctx['user']->id);

    $this->post(route('documents.shared.password.verify', $share->token), [
        'name' => 'Maria Rocha',
        'cpf'  => '529.982.247-25',
    ])->assertRedirect();

    expect($share->fresh()->password_revealed_at)->not->toBeNull();
});

test('10. an incorrect name is rejected even with the correct cpf', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', null, $ctx['user']->id);

    $this->post(route('documents.shared.password.verify', $share->token), [
        'name' => 'João Pereira',
        'cpf'  => '529.982.247-25',
    ])->assertSessionHasErrors('identity');

    expect($share->fresh()->password_revealed_at)->toBeNull();
});

test('11. an incorrect cpf is rejected even with the correct name', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', null, $ctx['user']->id);

    $this->post(route('documents.shared.password.verify', $share->token), [
        'name' => 'Maria Luiza',
        'cpf'  => '111.444.777-35',
    ])->assertSessionHasErrors('identity');

    expect($share->fresh()->password_revealed_at)->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────
// 15/16. visualização online e segredo antes da validação
// ─────────────────────────────────────────────────────────────────────────

test('15. viewing the document online works after identity is verified, using a single browser session', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', null, $ctx['user']->id);

    $verify = $this->post(route('documents.shared.password.verify', $share->token), [
        'name' => 'Maria Luiza',
        'cpf'  => '529.982.247-25',
    ]);
    $verify->assertRedirect();

    $this->get(route('documents.shared.view', $share->token))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('viewing the document online without verifying identity first is rejected', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', null, $ctx['user']->id);

    $this->get(route('documents.shared.view', $share->token))->assertStatus(403);
});

test('16. the password never appears on the page before identity is verified', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', null, $ctx['user']->id);

    $response = $this->get(route('documents.shared.password.show', $share->token));

    expect($response->getContent())->not->toContain($share->fresh()->password_encrypted);
});

// ─────────────────────────────────────────────────────────────────────────
// 17/18. senha nunca em log + auditoria registrada
// ─────────────────────────────────────────────────────────────────────────

test('17/18. sharing and revealing generate audit logs, and the password is never stored in log metadata', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', null, $ctx['user']->id);

    $this->post(route('documents.shared.password.verify', $share->token), [
        'name' => 'Maria Luiza',
        'cpf'  => '529.982.247-25',
    ]);
    $this->get(route('documents.shared.view', $share->token));

    $logs = DocumentShareLog::where('document_share_id', $share->id)->get();
    $actions = $logs->pluck('action')->all();

    expect($actions)->toContain('created')
        ->toContain('sent_email')
        ->toContain('password_revealed')
        ->toContain('document_viewed');

    $plainPassword = $share->fresh()->password_encrypted;
    foreach ($logs as $log) {
        $serialized = json_encode($log->metadata) . $log->action;
        expect($serialized)->not->toContain($plainPassword);
    }
});

// ─────────────────────────────────────────────────────────────────────────
// Segurança — token não previsível, S3 privado, rate limiting
// ─────────────────────────────────────────────────────────────────────────

test('security: the share token is long, random and not derived from the record id', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $service = app(\App\Services\Documents\DocumentShareService::class);

    $shareA = $service->share($document, $ctx['patient'], 'a@example.com', null, $ctx['user']->id);
    $shareB = $service->share($document, $ctx['patient'], 'b@example.com', null, $ctx['user']->id);

    // Tokens de shares consecutivos (IDs sequenciais no banco) não devem ter
    // nenhuma relação estrutural adivinhável entre si — bastante entropia
    // (48 chars) e completamente distintos, mesmo vindo de registros com
    // IDs vizinhos.
    expect(strlen($shareA->token))->toBeGreaterThanOrEqual(40)
        ->and(strlen($shareB->token))->toBeGreaterThanOrEqual(40)
        ->and($shareA->token)->not->toBe($shareB->token)
        ->and($shareA->token)->not->toBe((string) $shareA->id)
        ->and(ctype_digit($shareA->token))->toBeFalse();
});

test('security: the protected share pdf is not reachable on the public disk', function () {
    Storage::fake('s3');
    Storage::fake('public');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', null, $ctx['user']->id);

    expect(Storage::disk('public')->exists($share->storage_path))->toBeFalse();
});

test('security: a revoked share can no longer be viewed or have its password revealed', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', null, $ctx['user']->id);

    $this->actingAs($ctx['user'])
        ->post(route('patients.documents.shares.revoke', [$ctx['patient'], $document, $share]))
        ->assertRedirect();

    expect($share->fresh()->status)->toBe(DocumentShare::STATUS_REVOKED);

    $this->get(route('documents.shared.password.show', $share->token))
        ->assertOk()->assertViewHas('valid', false);

    $this->post(route('documents.shared.password.verify', $share->token), [
        'name' => 'Maria Luiza', 'cpf' => '529.982.247-25',
    ])->assertSessionHasErrors('identity');
});

test('5. revoking blocks new access but deliberately does not delete the already-sent pdf from storage', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', null, $ctx['user']->id);

    $storagePath = $share->storage_path;
    expect(Storage::disk('s3')->exists($storagePath))->toBeTrue();

    app(\App\Services\Documents\DocumentShareService::class)->revoke($share, $ctx['user']->id);

    // O objeto continua no S3 — revogar não "desenvia" uma cópia que o
    // destinatário já possa ter baixado, só bloqueia acesso NOVO pelo link.
    expect(Storage::disk('s3')->exists($storagePath))->toBeTrue();

    // Mas o link público não serve mais nada a partir de agora.
    $this->get(route('documents.shared.view', $share->token))->assertStatus(404);
});

test('security: repeated wrong identity attempts lock the share temporarily', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', null, $ctx['user']->id);

    for ($i = 0; $i < DocumentShare::MAX_IDENTITY_ATTEMPTS; $i++) {
        $this->post(route('documents.shared.password.verify', $share->token), [
            'name' => 'Nome Errado', 'cpf' => '111.444.777-35',
        ]);
    }

    expect($share->fresh()->isIdentityLocked())->toBeTrue();

    $this->get(route('documents.shared.password.show', $share->token))
        ->assertOk()->assertViewHas('valid', false)->assertViewHas('reason', 'locked');

    // Mesmo com dados corretos, a conta está travada.
    $this->post(route('documents.shared.password.verify', $share->token), [
        'name' => 'Maria Luiza', 'cpf' => '529.982.247-25',
    ])->assertSessionHasErrors('identity');
    expect($share->fresh()->password_revealed_at)->toBeNull();
});

test('sending the password by an unconfigured channel (whatsapp/sms) returns a ready-to-copy message instead of failing', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', 'Maria', $ctx['user']->id);

    $this->post(route('documents.shared.password.verify', $share->token), [
        'name' => 'Maria Luiza', 'cpf' => '529.982.247-25',
    ]);

    $response = $this->postJson(route('documents.shared.send-password', $share->token), ['channel' => 'whatsapp']);

    $response->assertOk()
        ->assertJsonPath('status', 'not_configured')
        ->assertJsonPath('channel', 'whatsapp');
    expect($response->json('message'))->toContain($share->fresh()->password_encrypted);
});

test('sending the password by email actually sends via the existing mail infrastructure', function () {
    Storage::fake('s3');
    Mail::fake();
    $ctx = setupDocumentShareContext();
    $document = createShareableDocument($ctx);
    $share = app(\App\Services\Documents\DocumentShareService::class)
        ->share($document, $ctx['patient'], 'x@example.com', 'Maria', $ctx['user']->id);

    $this->post(route('documents.shared.password.verify', $share->token), [
        'name' => 'Maria Luiza', 'cpf' => '529.982.247-25',
    ]);

    $this->postJson(route('documents.shared.send-password', $share->token), ['channel' => 'email'])
        ->assertOk()
        ->assertJsonPath('status', 'sent');

    Mail::assertSent(DocumentSharePasswordMail::class, fn ($mail) => $mail->hasTo('x@example.com'));
});
