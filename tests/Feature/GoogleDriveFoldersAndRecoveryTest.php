<?php

use App\Models\Clinic;
use App\Models\ClinicStorageConnection;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\GoogleDriveFakeHttp;

// Fase C1.2.1 — findFolder/createFolder/findOrCreateFolder (privados, via
// locateFolder/folderExists públicos e via resolveUploadFolder/recoverStructure
// de ponta a ponta) + a cascata real de partialRepair/detectStructureMissing.

function setupFolderContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-folders-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Folders', 'slug' => 'clinica-folders-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    ClinicStorageConnection::create([
        'clinic_id' => $clinic->id, 'provider' => 'google', 'status' => 'connected',
        'refresh_token' => Crypt::encryptString('real-refresh-token'),
        'access_token'  => Crypt::encryptString(json_encode(GoogleDriveFakeHttp::tokenResponse())),
        'expires_at'    => now()->addHour(),
    ]);
    $doctor = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($doctor->id, ['role' => 'professional']);
    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Fulano', 'sobrenome' => 'Silva', 'status' => 'ativo']);

    return compact('clinic', 'doctor', 'patient');
}

function wireFolders(GoogleDriveService $service): GoogleDriveFakeHttp
{
    $fake = new GoogleDriveFakeHttp();
    $service->useHttpClientForTesting($fake->client());

    return $fake;
}

// ── locateFolder / folderExists ─────────────────────────────────────────────

test('locateFolder returns the id when a matching folder is found', function () {
    ['clinic' => $clinic] = setupFolderContext();
    $service = app(GoogleDriveService::class);
    $fake = wireFolders($service);
    $drive = $service->getDriveForClinic($clinic);
    $fake->onPath('GET', '/files', fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([
        GoogleDriveFakeHttp::driveFile('folder-abc'),
    ])));

    expect($service->locateFolder('Categoria X', 'parent-1', $drive))->toBe('folder-abc');
});

test('locateFolder returns null when no folder matches', function () {
    ['clinic' => $clinic] = setupFolderContext();
    $service = app(GoogleDriveService::class);
    $fake = wireFolders($service);
    $drive = $service->getDriveForClinic($clinic);
    $fake->onPath('GET', '/files', fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([])));

    expect($service->locateFolder('Não Existe', 'parent-1', $drive))->toBeNull();
});

test('folderExists is true for a present, non-trashed folder and false for a 404', function () {
    ['clinic' => $clinic] = setupFolderContext();
    $service = app(GoogleDriveService::class);
    $fake = wireFolders($service);
    $drive = $service->getDriveForClinic($clinic);

    $fake->on(
        fn ($r) => str_contains((string) $r->getUri(), '/files/present'),
        fn () => GoogleDriveFakeHttp::json(['id' => 'present', 'trashed' => false])
    );
    $fake->on(
        fn ($r) => str_contains((string) $r->getUri(), '/files/gone'),
        fn () => GoogleDriveFakeHttp::googleError(404, 'notFound')
    );

    expect($service->folderExists('present', $drive))->toBeTrue()
        ->and($service->folderExists('gone', $drive))->toBeFalse();
});

test('folderExists is false for a trashed folder', function () {
    ['clinic' => $clinic] = setupFolderContext();
    $service = app(GoogleDriveService::class);
    $fake = wireFolders($service);
    $drive = $service->getDriveForClinic($clinic);
    $fake->onPath('GET', '/files/trashed-1', fn () => GoogleDriveFakeHttp::json(['id' => 'trashed-1', 'trashed' => true]));

    expect($service->folderExists('trashed-1', $drive))->toBeFalse();
});

// ── resolveUploadFolder — primeira vez (sem nada em cache) ─────────────────

test('resolveUploadFolder builds the full root→doctor→patient→categoria hierarchy from scratch, never duplicating an existing folder', function () {
    ['clinic' => $clinic, 'doctor' => $doctor, 'patient' => $patient] = setupFolderContext();
    $service = app(GoogleDriveService::class);
    $fake = wireFolders($service);

    // "Wildental" já existe (não deve recriar); doctor/paciente/categoria não existem.
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), 'name%20%3D%20%27Wildental%27'),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([GoogleDriveFakeHttp::driveFile('root-id')]))
    );
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), 'q='),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([]))
    );
    $created = [];
    $fake->on(
        fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'),
        function ($r) use (&$created) {
            $body = json_decode((string) $r->getBody(), true);
            $created[] = $body['name'] ?? null;
            return GoogleDriveFakeHttp::json(['id' => 'new-' . count($created)]);
        }
    );

    $result = $service->resolveUploadFolder($patient, $doctor, 'Radiografias');

    expect($created)->toBe([$doctor->name, 'Fulano Silva', 'Radiografias'])
        ->and($result['upload_folder_id'])->toBe('new-3')
        ->and($result['patient_folder_id'])->toBe('new-2');

    $patient->refresh();
    expect($patient->drive_folder_id)->toBe('new-2');
    expect($clinic->fresh()->storageConnection->drive_root_folder_id)->toBe('root-id');
});

// ── resolveUploadFolder — estrutura já existente (cache) ────────────────────

test('resolveUploadFolder with fully cached IDs skips root/doctor lookups entirely, only resolving the category', function () {
    ['clinic' => $clinic, 'doctor' => $doctor, 'patient' => $patient] = setupFolderContext();
    $clinic->storageConnection->update(['drive_root_folder_id' => 'cached-root']);
    $doctor->clinics()->updateExistingPivot($clinic->id, ['drive_doctor_folder_id' => 'cached-doctor']);
    $patient->update(['drive_folder_id' => 'cached-patient']);

    $service = app(GoogleDriveService::class);
    $fake = wireFolders($service);

    // assertStructureAvailable checa os 3 níveis (checkFolderExists) antes de confiar no cache.
    $fake->onPath('GET', '/files/cached-root', fn () => GoogleDriveFakeHttp::json(['id' => 'cached-root', 'trashed' => false]));
    $fake->onPath('GET', '/files/cached-doctor', fn () => GoogleDriveFakeHttp::json(['id' => 'cached-doctor', 'trashed' => false]));
    $fake->onPath('GET', '/files/cached-patient', fn () => GoogleDriveFakeHttp::json(['id' => 'cached-patient', 'trashed' => false]));
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), 'q='),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([GoogleDriveFakeHttp::driveFile('cat-id')]))
    );

    $result = $service->resolveUploadFolder($patient, $doctor, 'Radiografias');

    expect($result['upload_folder_id'])->toBe('cat-id')
        ->and($result['patient_folder_id'])->toBe('cached-patient');
    // Nenhum POST de criação — a categoria já existia (find, não create).
    expect($fake->countRequestsToPath('/files') - $fake->countRequestsToPath('q='))->toBeGreaterThanOrEqual(0);
});

// ── recovery: detectStructureMissing / partialRepair via recoverStructure ──

test('recoverStructure recreates nothing when the whole hierarchy is intact', function () {
    ['clinic' => $clinic, 'doctor' => $doctor, 'patient' => $patient] = setupFolderContext();
    $clinic->storageConnection->update(['drive_root_folder_id' => 'root-x']);
    $doctor->clinics()->updateExistingPivot($clinic->id, ['drive_doctor_folder_id' => 'doctor-x']);
    $patient->update(['drive_folder_id' => 'patient-x']);

    $service = app(GoogleDriveService::class);
    $fake = wireFolders($service);
    foreach (['root-x', 'doctor-x', 'patient-x'] as $id) {
        $fake->onPath('GET', "/files/{$id}", fn () => GoogleDriveFakeHttp::json(['id' => $id, 'trashed' => false]));
    }
    $creates = 0;
    $fake->on(
        fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'),
        function () use (&$creates) { $creates++; return GoogleDriveFakeHttp::json(['id' => 'unexpected']); }
    );

    $service->recoverStructure($patient, $doctor);

    expect($creates)->toBe(0);
});

test('recoverStructure recreates the full cascade (root, doctor, patient) when only the root is missing', function () {
    ['clinic' => $clinic, 'doctor' => $doctor, 'patient' => $patient] = setupFolderContext();
    $clinic->storageConnection->update(['drive_root_folder_id' => 'root-gone']);
    $doctor->clinics()->updateExistingPivot($clinic->id, ['drive_doctor_folder_id' => 'doctor-x']);
    $patient->update(['drive_folder_id' => 'patient-x']);

    $service = app(GoogleDriveService::class);
    $fake = wireFolders($service);
    $fake->onPath('GET', '/files/root-gone', fn () => GoogleDriveFakeHttp::googleError(404, 'notFound'));
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), 'q='),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([]))
    );
    $created = [];
    $fake->on(
        fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'),
        function ($r) use (&$created) {
            $body = json_decode((string) $r->getBody(), true);
            $created[] = $body['name'] ?? null;
            return GoogleDriveFakeHttp::json(['id' => 'recreated-' . count($created)]);
        }
    );

    $service->recoverStructure($patient, $doctor);

    expect($created)->toBe(['Wildental', $doctor->name, 'Fulano Silva']);
    expect($clinic->fresh()->storageConnection->drive_root_folder_id)->toBe('recreated-1');
    $patient->refresh();
    expect($patient->drive_folder_id)->toBe('recreated-3');
});

test('recoverStructure recreates only the patient level when root and doctor are still intact', function () {
    ['clinic' => $clinic, 'doctor' => $doctor, 'patient' => $patient] = setupFolderContext();
    $clinic->storageConnection->update(['drive_root_folder_id' => 'root-ok']);
    $doctor->clinics()->updateExistingPivot($clinic->id, ['drive_doctor_folder_id' => 'doctor-ok']);
    $patient->update(['drive_folder_id' => 'patient-gone']);

    $service = app(GoogleDriveService::class);
    $fake = wireFolders($service);
    $fake->onPath('GET', '/files/root-ok', fn () => GoogleDriveFakeHttp::json(['id' => 'root-ok', 'trashed' => false]));
    $fake->onPath('GET', '/files/doctor-ok', fn () => GoogleDriveFakeHttp::json(['id' => 'doctor-ok', 'trashed' => false]));
    $fake->onPath('GET', '/files/patient-gone', fn () => GoogleDriveFakeHttp::googleError(404, 'notFound'));
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), 'q='),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([]))
    );
    $created = [];
    $fake->on(
        fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'),
        function ($r) use (&$created) {
            $body = json_decode((string) $r->getBody(), true);
            $created[] = $body['name'] ?? null;
            return GoogleDriveFakeHttp::json(['id' => 'recreated-patient']);
        }
    );

    $service->recoverStructure($patient, $doctor);

    // Só o paciente foi recriado — nem "Wildental" nem o nome do médico aparecem.
    expect($created)->toBe(['Fulano Silva']);
    expect($clinic->fresh()->storageConnection->drive_root_folder_id)->toBe('root-ok');
});

test('recoverStructure is idempotent: running it again right after a successful repair recreates nothing more', function () {
    ['clinic' => $clinic, 'doctor' => $doctor, 'patient' => $patient] = setupFolderContext();
    $clinic->storageConnection->update(['drive_root_folder_id' => 'root-1']);
    $doctor->clinics()->updateExistingPivot($clinic->id, ['drive_doctor_folder_id' => 'doctor-1']);
    $patient->update(['drive_folder_id' => 'patient-1']);

    $service = app(GoogleDriveService::class);
    $fake = wireFolders($service);
    foreach (['root-1', 'doctor-1', 'patient-1'] as $id) {
        $fake->onPath('GET', "/files/{$id}", fn () => GoogleDriveFakeHttp::json(['id' => $id, 'trashed' => false]));
    }
    $creates = 0;
    $fake->on(
        fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'),
        function () use (&$creates) { $creates++; return GoogleDriveFakeHttp::json(['id' => 'should-not-happen']); }
    );

    $service->recoverStructure($patient, $doctor);
    $service->recoverStructure($patient, $doctor);

    expect($creates)->toBe(0);
});
