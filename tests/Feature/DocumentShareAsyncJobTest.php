<?php

use App\Jobs\ArchiveDocumentToDriveJob;
use App\Jobs\GenerateAndSendDocumentShareJob;
use App\Mail\DocumentShareMail;
use App\Models\AnamnesisInstance;
use App\Models\Clinic;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentShare;
use App\Models\DocumentShareLog;
use App\Models\DocumentTemplate;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\User;
use App\Services\Documents\DocumentShareService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Fase B5 — o compartilhamento de documento deixou de gerar o PDF protegido
 * + subir pro S3 + mandar o e-mail dentro da requisição HTTP (medido:
 * ~5.4s médios só na geração/criptografia do PDF, localmente, sem contar a
 * rede do S3). Agora share() só grava a linha (senha já cifrada em repouso)
 * e despacha GenerateAndSendDocumentShareJob — o trabalho pesado roda nele.
 */
function setupDocumentShareAsyncContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-share-async-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Share Async', 'slug' => 'clinica-share-async-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);
    $patient = Patient::create([
        'clinic_id' => $clinic->id, 'nome' => 'Maria', 'sobrenome' => 'Async', 'status' => 'ativo',
        'cpf' => '529.982.247-25', 'email' => 'maria-async@example.com',
    ]);
    $category = DocumentCategory::create([
        'clinic_id' => $clinic->id, 'name' => 'Termos', 'slug' => 'termos-async-' . uniqid(),
        'is_system' => false, 'is_active' => true,
    ]);
    $template = DocumentTemplate::create([
        'clinic_id' => $clinic->id, 'category_id' => $category->id, 'name' => 'Termo', 'slug' => 'termo-async-' . uniqid(),
        'requires_patient_signature' => false, 'is_system' => false, 'created_by_id' => $user->id,
    ]);
    $template->createNewVersion('Termo', '<p>Conteúdo</p>', 'Criação', $user->id);
    $document = Document::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'template_id' => $template->id,
        'template_version_id' => $template->current_version_id, 'template_name' => $template->name,
        'professional_id' => $user->id, 'status' => 'completed', 'rendered_html' => '<p>Conteúdo</p>',
        'document_code' => 'DOC-ASYNC-' . uniqid(), 'created_by_id' => $user->id,
    ]);
    Storage::disk('s3')->put('documents/document-' . $document->id . '.pdf', '%PDF-1.4 fake');
    $document->update(['pdf_path' => 'documents/document-' . $document->id . '.pdf']);

    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic', 'patient', 'document');
}

test('share() returns fast: dispatches the job instead of doing the heavy work inline', function () {
    Storage::fake('s3');
    Queue::fake();
    ['user' => $user, 'patient' => $patient, 'document' => $document] = setupDocumentShareAsyncContext();

    $share = app(DocumentShareService::class)->share($document, $patient, 'dest@example.com', null, $user->id);

    expect($share->generation_status)->toBe(DocumentShare::GENERATION_PROCESSING)
        ->and($share->sent_at)->toBeNull()
        // Sem o job rodar (Queue::fake() intercepta), o PDF protegido não
        // deveria existir ainda — prova de que o trabalho pesado saiu da
        // requisição.
        ->and(Storage::disk('s3')->exists($share->storage_path))->toBeFalse();

    Queue::assertPushed(GenerateAndSendDocumentShareJob::class, fn ($job) => $job->shareId === $share->id);
});

test('running the job generates the pdf, uploads it, sends the email and marks the share sent', function () {
    Storage::fake('s3');
    Mail::fake();
    ['user' => $user, 'patient' => $patient, 'document' => $document] = setupDocumentShareAsyncContext();

    $share = app(DocumentShareService::class)->share($document, $patient, 'dest@example.com', null, $user->id);
    // QUEUE_CONNECTION=sync em teste já rodou o job dentro de share() — mas
    // vamos rodar de novo explicitamente aqui pra deixar a asserção
    // independente desse detalhe de configuração.
    (new GenerateAndSendDocumentShareJob($share->id))->handle(app(DocumentShareService::class));

    $share->refresh();
    expect($share->generation_status)->toBe(DocumentShare::GENERATION_SENT)
        ->and($share->sent_at)->not->toBeNull()
        ->and(Storage::disk('s3')->exists($share->storage_path))->toBeTrue();

    Mail::assertSent(DocumentShareMail::class, fn ($mail) => $mail->hasTo('dest@example.com'));
});

test('idempotency: running the job twice does not resend the email or redo the work', function () {
    Storage::fake('s3');
    Mail::fake();
    ['user' => $user, 'patient' => $patient, 'document' => $document] = setupDocumentShareAsyncContext();

    $share = app(DocumentShareService::class)->share($document, $patient, 'dest@example.com', null, $user->id);
    $service = app(DocumentShareService::class);

    (new GenerateAndSendDocumentShareJob($share->id))->handle($service);
    $sentAtFirstRun = $share->fresh()->sent_at;

    // Segunda execução (ex: retry tardio, redispatch acidental).
    (new GenerateAndSendDocumentShareJob($share->id))->handle($service);

    $share->refresh();
    expect($share->sent_at->eq($sentAtFirstRun))->toBeTrue();
    Mail::assertSent(DocumentShareMail::class, 1); // exatamente 1, nunca 2
    expect(DocumentShareLog::where('document_share_id', $share->id)->where('action', 'sent_email')->count())->toBe(1);
});

test('a permanently failing job flips generation_status to failed instead of leaving it stuck processing', function () {
    Storage::fake('s3');
    Mail::fake();
    Queue::fake(); // impede o job de rodar sozinho (sync driver em teste) — queremos ele parado em "processing".
    ['user' => $user, 'patient' => $patient, 'document' => $document] = setupDocumentShareAsyncContext();

    $share = app(DocumentShareService::class)->share($document, $patient, 'dest@example.com', null, $user->id);
    expect($share->generation_status)->toBe(DocumentShare::GENERATION_PROCESSING);

    // Simula o esgotamento das tentativas chamando failed() diretamente —
    // é exatamente o que o worker chama depois do último retry.
    $job = new GenerateAndSendDocumentShareJob($share->id);
    $job->failed(new \RuntimeException('S3 indisponível (simulado)'));

    expect($share->fresh()->generation_status)->toBe(DocumentShare::GENERATION_FAILED);
});

test('failed() never overwrites an already-sent share (idempotent even in the failure path)', function () {
    Storage::fake('s3');
    Mail::fake();
    ['user' => $user, 'patient' => $patient, 'document' => $document] = setupDocumentShareAsyncContext();

    $share = app(DocumentShareService::class)->share($document, $patient, 'dest@example.com', null, $user->id);
    (new GenerateAndSendDocumentShareJob($share->id))->handle(app(DocumentShareService::class));
    expect($share->fresh()->generation_status)->toBe(DocumentShare::GENERATION_SENT);

    // Um failed() tardio (ex: race entre retry e sucesso) não pode reverter
    // um share que já foi enviado com sucesso pra "failed".
    (new GenerateAndSendDocumentShareJob($share->id))->failed(new \RuntimeException('tardio'));

    expect($share->fresh()->generation_status)->toBe(DocumentShare::GENERATION_SENT);
});

test('the job payload never carries the plaintext password — only the share id', function () {
    $job = new GenerateAndSendDocumentShareJob(123);

    // A única propriedade de negócio do job é shareId (int) — as demais são
    // todas da trait Queueable (tries/backoff/connection/queue/...), nenhuma
    // delas carrega senha. serialize() é o formato real gravado na tabela
    // `jobs` — confirmamos que ele nunca contém texto que pareça senha.
    expect($job->shareId)->toBe(123);

    $serialized = serialize($job);
    expect($serialized)->not->toContain('password');
});

test('job retry configuration matches the house pattern for external-call jobs (tries=3, backoff=30)', function () {
    $job = new GenerateAndSendDocumentShareJob(1);

    expect($job->tries)->toBe(3)->and($job->backoff)->toBe(30);
});

test('an anamnesis share (not a document) also works end to end through the job', function () {
    Storage::fake('s3');
    Mail::fake();
    ['user' => $user, 'patient' => $patient] = setupDocumentShareAsyncContext();

    $template = \App\Models\AnamnesisTemplate::first() ?? \App\Models\AnamnesisTemplate::create([
        'name' => 'Anamnese', 'slug' => 'anamnese-async-' . uniqid(), 'version' => 1,
        'is_system' => false, 'is_active' => true,
    ]);
    $instance = AnamnesisInstance::create([
        'clinic_id' => $patient->clinic_id, 'patient_id' => $patient->id, 'template_id' => $template->id,
        'template_name' => $template->name, 'professional_id' => $user->id, 'status' => 'completed',
        'completed_at' => now(),
    ]);

    $share = app(DocumentShareService::class)->share($instance, $patient, 'dest@example.com', null, $user->id);
    (new GenerateAndSendDocumentShareJob($share->id))->handle(app(DocumentShareService::class));

    expect($share->fresh()->generation_status)->toBe(DocumentShare::GENERATION_SENT);
    expect(Storage::disk('s3')->exists($share->storage_path))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────
// ArchiveDocumentToDriveJob — arquivamento best-effort no Drive
// ─────────────────────────────────────────────────────────────────────────

test('generating a document dispatches the drive archive job instead of uploading inline', function () {
    Storage::fake('s3');
    Queue::fake();
    ['user' => $user, 'patient' => $patient] = setupDocumentShareAsyncContext();

    $category = DocumentCategory::create([
        'clinic_id' => $patient->clinic_id, 'name' => 'Receitas', 'slug' => 'receitas-async-' . uniqid(),
        'is_system' => false, 'is_active' => true,
    ]);
    $template = DocumentTemplate::create([
        'clinic_id' => $patient->clinic_id, 'category_id' => $category->id, 'name' => 'Receita', 'slug' => 'receita-async-' . uniqid(),
        'requires_patient_signature' => false, 'is_system' => false, 'created_by_id' => $user->id,
    ]);
    $template->createNewVersion('Receita', '<p>Conteúdo</p>', 'Criação', $user->id);
    $document = Document::create([
        'clinic_id' => $patient->clinic_id, 'patient_id' => $patient->id, 'template_id' => $template->id,
        'template_version_id' => $template->current_version_id, 'template_name' => $template->name,
        'professional_id' => $user->id, 'status' => 'completed', 'rendered_html' => '<p>Conteúdo</p>',
        'document_code' => 'DOC-ARCH-' . uniqid(), 'created_by_id' => $user->id,
    ]);

    app(\App\Services\Documents\DocumentPdfService::class)->generate($document, $user->id);

    Queue::assertPushed(ArchiveDocumentToDriveJob::class, fn ($job) => $job->documentId === $document->id);
});

test('the drive archive job silently no-ops if the document no longer exists', function () {
    // Não deve lançar exceção — mesma semântica best-effort de
    // DocumentDriveArchiver::archive() (que já engole qualquer falha).
    $job = new ArchiveDocumentToDriveJob(999999);

    $job->handle(app(\App\Services\Documents\DocumentDriveArchiver::class));

    expect(true)->toBeTrue(); // chegou até aqui sem exceção
});
