<?php

use App\Exceptions\DriveStructureMissingException;
use App\Models\Clinic;
use App\Models\ClinicStorageConnection;
use App\Models\DriveActivityLog;
use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Models\Plan;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\GoogleDriveFakeHttp;

function setupUploadContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-upload-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Upload', 'slug' => 'clinica-upload-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    ClinicStorageConnection::create([
        'clinic_id' => $clinic->id, 'provider' => 'google', 'status' => 'connected',
        'refresh_token' => Crypt::encryptString('real-refresh-token'),
        'access_token'  => Crypt::encryptString(json_encode(GoogleDriveFakeHttp::tokenResponse())),
        'expires_at'    => now()->addHour(),
        'drive_root_folder_id' => 'root-x',
    ]);
    $doctor = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($doctor->id, ['role' => 'professional']);
    $doctor->clinics()->updateExistingPivot($clinic->id, ['drive_doctor_folder_id' => 'doctor-x']);
    $patient = Patient::create([
        'clinic_id' => $clinic->id, 'nome' => 'Beltrano', 'sobrenome' => 'Souza', 'status' => 'ativo',
        'drive_folder_id' => 'patient-x',
    ]);

    $tmp = tempnam(sys_get_temp_dir(), 'gdrive-test-');
    file_put_contents($tmp, 'conteúdo fake da imagem');

    return compact('clinic', 'doctor', 'patient') + ['filePath' => $tmp];
}

function wireUpload(GoogleDriveService $service): GoogleDriveFakeHttp
{
    $fake = new GoogleDriveFakeHttp();
    $service->useHttpClientForTesting($fake->client());

    return $fake;
}

function stubStructureIntact(GoogleDriveFakeHttp $fake): void
{
    foreach (['root-x', 'doctor-x', 'patient-x'] as $id) {
        $fake->onPath('GET', "/files/{$id}", fn () => GoogleDriveFakeHttp::json(['id' => $id, 'trashed' => false]));
    }
}

// ── uploadToKnownFolder — usado por UploadEvolutionPhotoJob ────────────────

test('uploadToKnownFolder uploads straight to the given folder and returns the real file id, with no PatientPhoto side effect', function () {
    ['patient' => $patient, 'filePath' => $filePath] = setupUploadContext();
    $service = app(GoogleDriveService::class);
    $fake = wireUpload($service);
    $captured = null;
    $fake->on(
        fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'),
        function ($r) use (&$captured) { $captured = (string) $r->getBody(); return GoogleDriveFakeHttp::json(['id' => 'uploaded-file-1']); }
    );

    $result = $service->uploadToKnownFolder($patient, 'folder-known', $filePath, 'evolucao.jpg', 'image/jpeg');

    expect($result['drive_file_id'])->toBe('uploaded-file-1')
        ->and(PatientPhoto::count())->toBe(0);
    expect($captured)->toContain('evolucao.jpg')->toContain('folder-known');

    unlink($filePath);
});

test('uploadToKnownFolder throws DriveStructureMissingException when the target folder is gone (404)', function () {
    ['patient' => $patient, 'filePath' => $filePath] = setupUploadContext();
    $service = app(GoogleDriveService::class);
    $fake = wireUpload($service);
    $fake->on(
        fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'),
        fn () => GoogleDriveFakeHttp::googleError(404, 'notFound')
    );

    expect(fn () => $service->uploadToKnownFolder($patient, 'folder-gone', $filePath, 'x.jpg', 'image/jpeg'))
        ->toThrow(DriveStructureMissingException::class);

    unlink($filePath);
});

// Fase C1.2.1, seção 13 — job/console context: nenhum Auth::user()/session()
// usado nesta chamada, só os objetos explicitamente passados. Roda sem
// $this->actingAs() e sem session(['current_clinic_id' => ...]) de propósito.
test('uploadToKnownFolder works correctly with zero HTTP/session/auth context, exactly like UploadEvolutionPhotoJob calls it', function () {
    ['patient' => $patient, 'filePath' => $filePath] = setupUploadContext();
    // Nenhum actingAs(), nenhum session() — só os objetos de domínio.
    $service = app(GoogleDriveService::class);
    $fake = wireUpload($service);
    $fake->on(
        fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'),
        fn () => GoogleDriveFakeHttp::json(['id' => 'job-context-file'])
    );

    $result = $service->uploadToKnownFolder($patient, 'folder-known', $filePath, 'x.jpg', 'image/jpeg');

    expect($result['drive_file_id'])->toBe('job-context-file');
    unlink($filePath);
});

// ── uploadPhoto ──────────────────────────────────────────────────────────────

test('uploadPhoto with a preResolvedFolderId skips all structure resolution and uploads directly', function () {
    ['patient' => $patient, 'doctor' => $doctor, 'filePath' => $filePath] = setupUploadContext();
    $service = app(GoogleDriveService::class);
    $fake = wireUpload($service);
    $fake->on(
        fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'),
        fn () => GoogleDriveFakeHttp::json(['id' => 'preresolved-upload'])
    );

    $result = $service->uploadPhoto(
        $patient, $doctor, $filePath, 'evolucao.jpg', 'image/jpeg',
        ['categoria' => 'Evoluções'], false, 'already-known-folder'
    );

    expect($result['photo'])->toBeInstanceOf(PatientPhoto::class)
        ->and($result['photo']->drive_file_id)->toBe('preresolved-upload')
        ->and($result['structure_recreated'])->toBeFalse();
    // Nenhuma checagem de estrutura — 0 GET a /files (só o POST de upload).
    expect($fake->countRequestsToPath('GET'))->toBe(0);

    unlink($filePath);
});

test('uploadPhoto with an established structure validates it, uploads, and creates the PatientPhoto row with the right attributes', function () {
    ['patient' => $patient, 'doctor' => $doctor, 'filePath' => $filePath] = setupUploadContext();
    $service = app(GoogleDriveService::class);
    $fake = wireUpload($service);
    stubStructureIntact($fake);
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), 'q='),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([GoogleDriveFakeHttp::driveFile('cat-folder')]))
    );
    $fake->on(
        fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'),
        fn () => GoogleDriveFakeHttp::json(['id' => 'uploaded-2'])
    );

    $result = $service->uploadPhoto(
        $patient, $doctor, $filePath, 'raio-x.jpg', 'image/jpeg',
        ['categoria' => 'Radiografias', 'dente' => '11']
    );

    $photo = $result['photo'];
    expect($photo->drive_file_id)->toBe('uploaded-2')
        ->and($photo->drive_folder_id)->toBe('cat-folder')
        ->and($photo->categoria)->toBe('Radiografias')
        ->and($photo->dente)->toBe('11')
        ->and($photo->status)->toBe('active')
        ->and($photo->clinic_id)->toBe($patient->clinic_id)
        ->and($result['structure_recreated'])->toBeFalse();

    unlink($filePath);
});

test('uploadPhoto with authorizeRecovery=true forces a rebuild regardless of current state and logs the disaster-recovery events', function () {
    ['clinic' => $clinic, 'patient' => $patient, 'doctor' => $doctor, 'filePath' => $filePath] = setupUploadContext();
    $service = app(GoogleDriveService::class);
    $fake = wireUpload($service);
    // Estrutura age como intacta, mas authorizeRecovery força partialRepair mesmo assim.
    stubStructureIntact($fake);
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), 'q='),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([]))
    );
    $fake->on(
        fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'),
        fn () => GoogleDriveFakeHttp::json(['id' => 'recovered-upload'])
    );

    $result = $service->uploadPhoto($patient, $doctor, $filePath, 'x.jpg', 'image/jpeg', [], true);

    expect($result['structure_recreated'])->toBeTrue();
    expect(DriveActivityLog::where('patient_id', $patient->id)->where('event_type', 'structure_recovery_authorized')->exists())->toBeTrue();
    expect(DriveActivityLog::where('patient_id', $patient->id)->where('event_type', 'upload_resumed')->exists())->toBeTrue();

    unlink($filePath);
});

test('uploadPhoto throws DriveStructureMissingException when the upload itself hits a 404 and recovery was not authorized', function () {
    ['patient' => $patient, 'doctor' => $doctor, 'filePath' => $filePath] = setupUploadContext();
    $service = app(GoogleDriveService::class);
    $fake = wireUpload($service);
    stubStructureIntact($fake);
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), 'q='),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([GoogleDriveFakeHttp::driveFile('cat-folder')]))
    );
    $fake->on(
        fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'),
        fn () => GoogleDriveFakeHttp::googleError(404, 'notFound')
    );

    expect(fn () => $service->uploadPhoto($patient, $doctor, $filePath, 'x.jpg', 'image/jpeg', ['categoria' => 'X']))
        ->toThrow(DriveStructureMissingException::class);
    expect(PatientPhoto::count())->toBe(0);

    unlink($filePath);
});

// ── streamPhoto — conteúdo/headers ──────────────────────────────────────────

test('streamPhoto returns the exact bytes, mime type and content-length of the Drive file', function () {
    ['patient' => $patient, 'clinic' => $clinic] = setupUploadContext();
    $photo = PatientPhoto::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id,
        'drive_file_id' => 'stream-me', 'filename' => 'foto.png', 'mime_type' => 'image/png', 'status' => 'active',
    ]);
    $service = app(GoogleDriveService::class);
    $fake = wireUpload($service);
    $bytes = random_bytes(128);
    $fake->onPath('GET', '/files/stream-me', fn () => GoogleDriveFakeHttp::raw($bytes, 200, ['Content-Type' => 'image/png']));

    $response = $service->streamPhoto($photo);

    expect($response->getContent())->toBe($bytes)
        ->and($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->headers->get('Content-Length'))->toBe((string) strlen($bytes))
        ->and($response->getStatusCode())->toBe(200);
});

test('streamPhoto propagates a Google_Service_Exception when the file no longer exists on Drive', function () {
    ['patient' => $patient, 'clinic' => $clinic] = setupUploadContext();
    $photo = PatientPhoto::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id,
        'drive_file_id' => 'gone-file', 'filename' => 'foto.png', 'mime_type' => 'image/png', 'status' => 'active',
    ]);
    $service = app(GoogleDriveService::class);
    $fake = wireUpload($service);
    $fake->onPath('GET', '/files/gone-file', fn () => GoogleDriveFakeHttp::googleError(404, 'notFound'));

    expect(fn () => $service->streamPhoto($photo))->toThrow(Google_Service_Exception::class);
});
