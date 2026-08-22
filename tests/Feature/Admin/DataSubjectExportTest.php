<?php

use App\Jobs\GenerateDataSubjectExportJob;
use App\Models\AccessLog;
use App\Models\AnamnesisInstance;
use App\Models\AnamnesisTemplate;
use App\Models\Clinic;
use App\Models\DataSubjectExport;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\Patient;
use App\Models\PatientInvite;
use App\Models\Plan;
use App\Models\SystemAdmin;
use App\Models\User;
use App\Services\Admin\DataSubjectExportService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

// Ferramenta interna do Backoffice pra atender solicitação LGPD recebida
// via chat/suporte — sempre por-ID, individual, nunca em massa. Mesmo
// padrão assíncrono de DocumentShare/GenerateAndSendDocumentShareJob (ver
// tests/Feature/DocumentShareAsyncJobTest.php), aplicado a um caso novo.

function setupDataSubjectExportContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-dse-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1,
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica DSE', 'slug' => 'clinica-dse-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $clinicB = Clinic::create([
        'name' => 'Clínica DSE B', 'slug' => 'clinica-dse-b-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $admin = User::factory()->create(['email_verified_at' => now(), 'name' => 'Admin DSE']);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    $patient = Patient::create([
        'clinic_id' => $clinic->id, 'nome' => 'Maria', 'sobrenome' => 'Titular',
        'status' => 'ativo', 'cpf' => '529.982.247-25', 'email' => 'maria-titular@example.com',
    ]);
    $patientB = Patient::create([
        'clinic_id' => $clinicB->id, 'nome' => 'Outro', 'sobrenome' => 'DaClinicaB', 'status' => 'ativo',
    ]);

    return compact('plan', 'clinic', 'clinicB', 'admin', 'patient', 'patientB');
}

test('a system admin can search users', function () {
    ['admin' => $admin] = setupDataSubjectExportContext();
    $target = User::factory()->create(['email_verified_at' => now(), 'name' => 'Fulano Buscavel', 'email' => 'fulano-buscavel@example.com']);

    $this->actingAs($admin)
        ->getJson(route('admin.exports.subjects.users.search', ['q' => 'Buscavel']))
        ->assertOk()
        ->assertJsonFragment(['id' => $target->id, 'name' => 'Fulano Buscavel']);
});

test('a system admin can search patients across any clinic', function () {
    ['admin' => $admin, 'patient' => $patient] = setupDataSubjectExportContext();

    $this->actingAs($admin)
        ->getJson(route('admin.exports.subjects.patients.search', ['q' => 'Titular']))
        ->assertOk()
        ->assertJsonFragment(['id' => $patient->id, 'name' => 'Maria Titular']);
});

test('exporting a user returns a JSON with the account fields, never password/remember_token', function () {
    ['admin' => $admin] = setupDataSubjectExportContext();
    $target = User::factory()->create(['email_verified_at' => now(), 'name' => 'Alvo Export']);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.exports.subjects.users.export', $target->id))
        ->assertOk();

    $response->assertJsonPath('usuario.name', 'Alvo Export');
    expect($response->json('usuario'))->not->toHaveKey('password')->not->toHaveKey('remember_token');

    expect(AccessLog::where('action', 'admin_user_data_exported')->where('metadata->subject_id', $target->id)->exists())->toBeTrue();
});

test('starting a patient export creates a processing record and dispatches the job instead of doing the work inline', function () {
    Queue::fake();
    ['admin' => $admin, 'patient' => $patient] = setupDataSubjectExportContext();

    $response = $this->actingAs($admin)
        ->postJson(route('admin.exports.subjects.patients.export', $patient->id))
        ->assertStatus(202);

    $exportId = $response->json('export_id');
    expect(DataSubjectExport::find($exportId)->generation_status)->toBe(DataSubjectExport::GENERATION_PROCESSING);

    Queue::assertPushed(GenerateDataSubjectExportJob::class, fn ($job) => $job->exportId === $exportId);

    $log = AccessLog::where('action', 'admin_patient_data_exported')->latest()->first();
    expect($log)->not->toBeNull()
        ->and($log->clinic_id)->toBe($patient->clinic_id)
        ->and($log->description)->not->toContain('Maria')
        ->and($log->description)->not->toContain($patient->cpf);
});

test('running the job builds a zip with the expected categories and manifest, and marks the export ready', function () {
    Storage::fake('s3');
    ['admin' => $admin, 'clinic' => $clinic, 'patient' => $patient] = setupDataSubjectExportContext();

    $category = DocumentCategory::create([
        'clinic_id' => $clinic->id, 'name' => 'Termos', 'slug' => 'termos-dse-' . uniqid(),
        'is_system' => false, 'is_active' => true,
    ]);
    $template = DocumentTemplate::create([
        'clinic_id' => $clinic->id, 'category_id' => $category->id, 'name' => 'Termo', 'slug' => 'termo-dse-' . uniqid(),
        'requires_patient_signature' => false, 'is_system' => false, 'created_by_id' => $admin->id,
    ]);
    $template->createNewVersion('Termo', '<p>Conteúdo</p>', 'Criação', $admin->id);
    $document = Document::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'template_id' => $template->id,
        'template_version_id' => $template->current_version_id, 'template_name' => $template->name,
        'professional_id' => $admin->id, 'status' => 'completed', 'rendered_html' => '<p>Conteúdo</p>',
        'document_code' => 'DOC-DSE-' . uniqid(), 'created_by_id' => $admin->id,
    ]);
    Storage::disk('s3')->put('documents/document-' . $document->id . '.pdf', '%PDF-1.4 fake');
    $document->update(['pdf_path' => 'documents/document-' . $document->id . '.pdf']);

    $export = app(DataSubjectExportService::class)->startPatientExport($patient, $admin);
    (new GenerateDataSubjectExportJob($export->id))->handle(app(DataSubjectExportService::class));

    $export->refresh();
    expect($export->generation_status)->toBe(DataSubjectExport::GENERATION_READY)
        ->and($export->storage_path)->not->toBeNull()
        ->and(Storage::disk('s3')->exists($export->storage_path))->toBeTrue();

    $zipContents = Storage::disk('s3')->get($export->storage_path);
    $tmpZip = tempnam(sys_get_temp_dir(), 'dse-test-') . '.zip';
    file_put_contents($tmpZip, $zipContents);

    $zip = new \ZipArchive();
    $zip->open($tmpZip);

    expect($zip->locateName('manifest.json'))->not->toBeFalse()
        ->and($zip->locateName('perfil/dados-cadastrais.json'))->not->toBeFalse()
        ->and($zip->locateName('documentos/documentos.json'))->not->toBeFalse()
        ->and($zip->locateName('documentos/arquivos/' . $document->document_code . '.pdf'))->not->toBeFalse();

    $manifest = json_decode($zip->getFromName('manifest.json'), true);
    expect($manifest['paciente_id'])->toBe($patient->id)
        ->and($manifest['clinica_id'])->toBe($clinic->id)
        ->and($manifest)->toHaveKey('contagem')
        ->and($manifest)->toHaveKey('itens_com_falha');

    $zip->close();
    @unlink($tmpZip);
});

test('the zip never contains technical access tokens for public flows (invite, document validation/signature, anamnesis validation)', function () {
    Storage::fake('s3');
    ['admin' => $admin, 'clinic' => $clinic, 'patient' => $patient] = setupDataSubjectExportContext();

    $category = DocumentCategory::create([
        'clinic_id' => $clinic->id, 'name' => 'Termos', 'slug' => 'termos-tok-' . uniqid(),
        'is_system' => false, 'is_active' => true,
    ]);
    $template = DocumentTemplate::create([
        'clinic_id' => $clinic->id, 'category_id' => $category->id, 'name' => 'Termo', 'slug' => 'termo-tok-' . uniqid(),
        'requires_patient_signature' => false, 'is_system' => false, 'created_by_id' => $admin->id,
    ]);
    $template->createNewVersion('Termo', '<p>Conteúdo</p>', 'Criação', $admin->id);
    Document::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'template_id' => $template->id,
        'template_version_id' => $template->current_version_id, 'template_name' => $template->name,
        'professional_id' => $admin->id, 'status' => 'completed', 'rendered_html' => '<p>Conteúdo</p>',
        'document_code' => 'DOC-TOK-' . uniqid(), 'created_by_id' => $admin->id,
        'validation_token' => 'SECRET-VALIDATION-TOKEN',
        'signature_token' => 'SECRET-SIGNATURE-TOKEN',
        'signature_token_expires_at' => now()->addDay(),
    ]);

    $anamnesisTemplate = AnamnesisTemplate::create([
        'clinic_id' => $clinic->id, 'name' => 'Modelo Token', 'slug' => 'modelo-tok-' . uniqid(),
        'is_system' => false, 'is_active' => true,
    ]);
    AnamnesisInstance::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'template_id' => $anamnesisTemplate->id,
        'template_name' => $anamnesisTemplate->name, 'template_version' => 1, 'professional_id' => $admin->id,
        'status' => 'rascunho', 'progress' => 0, 'started_at' => now(), 'anamnesis_date' => now(),
        'validation_token' => 'SECRET-ANAMNESIS-TOKEN',
    ]);

    PatientInvite::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'kind' => 'cadastro',
        'token' => 'SECRET-INVITE-TOKEN', 'status' => 'enviado', 'channel' => 'email',
        'created_by' => $admin->id,
    ]);

    $export = app(DataSubjectExportService::class)->startPatientExport($patient, $admin);
    (new GenerateDataSubjectExportJob($export->id))->handle(app(DataSubjectExportService::class));

    $tmpZip = tempnam(sys_get_temp_dir(), 'dse-token-test-') . '.zip';
    file_put_contents($tmpZip, Storage::disk('s3')->get($export->fresh()->storage_path));
    $zip = new \ZipArchive();
    $zip->open($tmpZip);

    $documentsJson = $zip->getFromName('documentos/documentos.json');
    $anamneseJson = $zip->getFromName('anamnese/instancias.json');
    $conviteJson = $zip->getFromName('convite/convites.json');

    foreach (['SECRET-VALIDATION-TOKEN', 'SECRET-SIGNATURE-TOKEN', 'SECRET-ANAMNESIS-TOKEN', 'SECRET-INVITE-TOKEN'] as $secret) {
        expect($documentsJson)->not->toContain($secret);
        expect($anamneseJson)->not->toContain($secret);
        expect($conviteJson)->not->toContain($secret);
    }

    expect(json_decode($documentsJson, true)[0])->not->toHaveKey('validation_token')->not->toHaveKey('signature_token');
    expect(json_decode($anamneseJson, true)[0])->not->toHaveKey('validation_token');
    expect(json_decode($conviteJson, true)[0])->not->toHaveKey('token');

    // O resto do registro continua presente — a sanitização não apaga o
    // documento/instância/convite inteiro, só os campos de token.
    expect(json_decode($documentsJson, true)[0])->toHaveKey('document_code')->toHaveKey('status');
    expect(json_decode($anamneseJson, true)[0])->toHaveKey('status')->toHaveKey('progress');
    expect(json_decode($conviteJson, true)[0])->toHaveKey('kind')->toHaveKey('status');

    $zip->close();
    @unlink($tmpZip);
});

test('idempotency: running the job twice does not rebuild the zip', function () {
    Storage::fake('s3');
    ['admin' => $admin, 'patient' => $patient] = setupDataSubjectExportContext();

    $export = app(DataSubjectExportService::class)->startPatientExport($patient, $admin);
    (new GenerateDataSubjectExportJob($export->id))->handle(app(DataSubjectExportService::class));
    $pathAfterFirstRun = $export->fresh()->storage_path;

    (new GenerateDataSubjectExportJob($export->id))->handle(app(DataSubjectExportService::class));

    expect($export->fresh()->storage_path)->toBe($pathAfterFirstRun);
});

test('a permanently failing job flips generation_status to failed with a reason', function () {
    Queue::fake(); // QUEUE_CONNECTION=sync em teste rodaria o job dentro de startPatientExport() —
    // queremos ele parado em "processing" pra testar failed() isoladamente.
    ['admin' => $admin, 'patient' => $patient] = setupDataSubjectExportContext();
    $export = app(DataSubjectExportService::class)->startPatientExport($patient, $admin);

    (new GenerateDataSubjectExportJob($export->id))->failed(new \RuntimeException('S3 indisponível (simulado)'));

    expect($export->fresh()->generation_status)->toBe(DataSubjectExport::GENERATION_FAILED)
        ->and($export->fresh()->generation_failed_reason)->toContain('S3 indisponível');
});

test('job retry configuration matches the house pattern for external-call jobs (tries=3, backoff=30)', function () {
    $job = new GenerateDataSubjectExportJob(1);

    expect($job->tries)->toBe(3)->and($job->backoff)->toBe(30);
});

test('a regular clinic user gets 403 on every data-subject export route', function () {
    ['clinic' => $clinic, 'patient' => $patient] = setupDataSubjectExportContext();
    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user)->getJson(route('admin.exports.subjects.users.search', ['q' => 'a']))->assertForbidden();
    $this->actingAs($user)->postJson(route('admin.exports.subjects.users.export', $user->id))->assertForbidden();
    $this->actingAs($user)->getJson(route('admin.exports.subjects.patients.search', ['q' => 'a']))->assertForbidden();
    $this->actingAs($user)->postJson(route('admin.exports.subjects.patients.export', $patient->id))->assertForbidden();
});

test('an arbitrary/nonexistent user or patient id never leaks data — 404, not a silent empty success', function () {
    ['admin' => $admin] = setupDataSubjectExportContext();

    $this->actingAs($admin)->postJson(route('admin.exports.subjects.users.export', 999999))->assertNotFound();
    $this->actingAs($admin)->postJson(route('admin.exports.subjects.patients.export', 999999))->assertNotFound();
});

test('tenant isolation: patient B never leaks into patient A export, they are fully independent exports', function () {
    Storage::fake('s3');
    ['admin' => $admin, 'clinic' => $clinicA, 'clinicB' => $clinicB, 'patient' => $patientA, 'patientB' => $patientB] = setupDataSubjectExportContext();

    $exportA = app(DataSubjectExportService::class)->startPatientExport($patientA, $admin);
    (new GenerateDataSubjectExportJob($exportA->id))->handle(app(DataSubjectExportService::class));

    $zip = new \ZipArchive();
    $tmpZip = tempnam(sys_get_temp_dir(), 'dse-tenant-') . '.zip';
    file_put_contents($tmpZip, Storage::disk('s3')->get($exportA->fresh()->storage_path));
    $zip->open($tmpZip);
    $manifest = json_decode($zip->getFromName('manifest.json'), true);

    expect($manifest['paciente_id'])->toBe($patientA->id)
        ->and($manifest['clinica_id'])->toBe($clinicA->id)
        ->and($manifest['paciente_id'])->not->toBe($patientB->id)
        ->and($manifest['clinica_id'])->not->toBe($clinicB->id);

    $zip->close();
    @unlink($tmpZip);
});

test('regression: the 7 original administrative datasets are untouched', function () {
    expect(array_keys(\App\Services\Admin\ExportService::DATASETS))->toBe([
        'clinics', 'users', 'clinic_users', 'subscriptions', 'plans', 'referrals', 'logs',
    ]);
});

test('downloading a ready export streams the zip; a not-ready export is not downloadable', function () {
    Storage::fake('s3');
    Queue::fake(); // mantém o export em "processing" até rodarmos o job manualmente abaixo.
    ['admin' => $admin, 'patient' => $patient] = setupDataSubjectExportContext();

    $processingExport = app(DataSubjectExportService::class)->startPatientExport($patient, $admin);
    $this->actingAs($admin)->get(route('admin.exports.subjects.download', $processingExport->id))->assertNotFound();

    (new GenerateDataSubjectExportJob($processingExport->id))->handle(app(DataSubjectExportService::class));

    $this->actingAs($admin)->get(route('admin.exports.subjects.download', $processingExport->id))->assertOk();
});
