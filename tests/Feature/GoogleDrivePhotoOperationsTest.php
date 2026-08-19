<?php

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

function setupPhotoOpsContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-photoops-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Fotos', 'slug' => 'clinica-fotos-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    ClinicStorageConnection::create([
        'clinic_id' => $clinic->id, 'provider' => 'google', 'status' => 'connected',
        'refresh_token' => Crypt::encryptString('real-refresh-token'),
        'access_token'  => Crypt::encryptString(json_encode(GoogleDriveFakeHttp::tokenResponse())),
        'expires_at'    => now()->addHour(),
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'professional']);
    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Ciclana', 'sobrenome' => 'Alves', 'status' => 'ativo', 'drive_folder_id' => 'patient-folder']);

    return compact('clinic', 'user', 'patient');
}

function wirePhotoOps(GoogleDriveService $service): GoogleDriveFakeHttp
{
    $fake = new GoogleDriveFakeHttp();
    $service->useHttpClientForTesting($fake->client());

    return $fake;
}

// ── renamePhoto ──────────────────────────────────────────────────────────────

test('renamePhoto renames the file in Drive and syncs the new filename/attributes in the database, without touching the folder', function () {
    ['clinic' => $clinic, 'user' => $user, 'patient' => $patient] = setupPhotoOpsContext();
    $photo = PatientPhoto::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'drive_file_id' => 'file-1',
        'drive_folder_id' => 'cat-a-folder', 'filename' => 'Antigo.jpg', 'mime_type' => 'image/jpeg', 'categoria' => 'Radiografias',
        'subcategoria' => 'Antigo', 'status' => 'active',
    ]);
    $service = app(GoogleDriveService::class);
    $fake = wirePhotoOps($service);
    $renameCalled = false;
    $fake->onPath('PATCH', '/files/file-1', function ($r) use (&$renameCalled) {
        $renameCalled = true;
        return GoogleDriveFakeHttp::json(['id' => 'file-1']);
    });

    $service->renamePhoto($photo, 'Novo Nome', null, null, 'desc', null, $user);

    expect($renameCalled)->toBeTrue();
    $photo->refresh();
    expect($photo->filename)->toBe('Novo Nome.jpg')
        ->and($photo->subcategoria)->toBe('Novo Nome')
        ->and($photo->description)->toBe('desc')
        ->and($photo->drive_folder_id)->toBe('cat-a-folder');
    expect(DriveActivityLog::where('photo_id', $photo->id)->where('event_type', 'file_renamed')->exists())->toBeTrue();
});

test('renamePhoto moves the file to a new category folder when the category changes, logging file_moved', function () {
    ['clinic' => $clinic, 'user' => $user, 'patient' => $patient] = setupPhotoOpsContext();
    $photo = PatientPhoto::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'drive_file_id' => 'file-2',
        'drive_folder_id' => 'old-cat-folder', 'filename' => 'X.jpg', 'mime_type' => 'image/jpeg', 'categoria' => 'Radiografias',
        'subcategoria' => 'X', 'status' => 'active',
    ]);
    $service = app(GoogleDriveService::class);
    $fake = wirePhotoOps($service);
    $fake->onPath('PATCH', '/files/file-2', fn () => GoogleDriveFakeHttp::json(['id' => 'file-2']));
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), 'q='),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([GoogleDriveFakeHttp::driveFile('new-cat-folder')]))
    );

    $service->renamePhoto($photo, 'X', null, 'Documentos', null, null, $user);

    $photo->refresh();
    expect($photo->categoria)->toBe('Documentos')
        ->and($photo->drive_folder_id)->toBe('new-cat-folder');
    $log = DriveActivityLog::where('photo_id', $photo->id)->where('event_type', 'file_moved')->first();
    expect($log)->not->toBeNull()
        ->and($log->metadata['drive_old_folder_id'])->toBe('old-cat-folder')
        ->and($log->metadata['drive_new_folder_id'])->toBe('new-cat-folder');
});

// ── deletePhotoFromSystem ────────────────────────────────────────────────────

test('deletePhotoFromSystem deletes from Drive, logs before deletion, then hard-deletes the DB row', function () {
    ['clinic' => $clinic, 'user' => $user, 'patient' => $patient] = setupPhotoOpsContext();
    $photo = PatientPhoto::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'drive_file_id' => 'to-delete',
        'filename' => 'x.jpg', 'mime_type' => 'image/jpeg', 'status' => 'active',
    ]);
    $photoId = $photo->id;
    $service = app(GoogleDriveService::class);
    $fake = wirePhotoOps($service);
    $deleteCalled = false;
    $fake->onPath('DELETE', '/files/to-delete', function () use (&$deleteCalled) {
        $deleteCalled = true;
        return GoogleDriveFakeHttp::raw('', 204);
    });

    $service->deletePhotoFromSystem($photo, $user);

    expect($deleteCalled)->toBeTrue()
        ->and(PatientPhoto::find($photoId))->toBeNull();
    expect(DriveActivityLog::where('event_type', 'file_deleted_system')->where('patient_id', $patient->id)->exists())->toBeTrue();
});

test('deletePhotoFromSystem still deletes the DB row even when the file is already gone from Drive (404 tolerated)', function () {
    ['clinic' => $clinic, 'user' => $user, 'patient' => $patient] = setupPhotoOpsContext();
    $photo = PatientPhoto::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'drive_file_id' => 'already-gone',
        'filename' => 'x.jpg', 'mime_type' => 'image/jpeg', 'status' => 'active',
    ]);
    $photoId = $photo->id;
    $service = app(GoogleDriveService::class);
    $fake = wirePhotoOps($service);
    $fake->onPath('DELETE', '/files/already-gone', fn () => GoogleDriveFakeHttp::googleError(404, 'notFound'));

    $service->deletePhotoFromSystem($photo, $user);

    expect(PatientPhoto::find($photoId))->toBeNull();
});

test('deletePhotoFromSystem propagates a non-404 Drive error and does NOT delete the DB row', function () {
    ['clinic' => $clinic, 'user' => $user, 'patient' => $patient] = setupPhotoOpsContext();
    $photo = PatientPhoto::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'drive_file_id' => 'boom',
        'filename' => 'x.jpg', 'mime_type' => 'image/jpeg', 'status' => 'active',
    ]);
    $photoId = $photo->id;
    $service = app(GoogleDriveService::class);
    $fake = wirePhotoOps($service);
    $fake->onPath('DELETE', '/files/boom', fn () => GoogleDriveFakeHttp::googleError(500, 'internalError'));

    expect(fn () => $service->deletePhotoFromSystem($photo, $user))->toThrow(Google_Service_Exception::class);
    expect(PatientPhoto::find($photoId))->not->toBeNull();
});

// ── markPhotoAsRemoved ───────────────────────────────────────────────────────

test('markPhotoAsRemoved sets status=removed and logs the event', function () {
    ['clinic' => $clinic, 'patient' => $patient] = setupPhotoOpsContext();
    $photo = PatientPhoto::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'drive_file_id' => 'x',
        'filename' => 'x.jpg', 'mime_type' => 'image/jpeg', 'status' => 'active',
    ]);
    $service = app(GoogleDriveService::class);

    $service->markPhotoAsRemoved($photo);

    expect($photo->fresh()->status)->toBe('removed');
    expect(DriveActivityLog::where('photo_id', $photo->id)->where('event_type', 'file_deleted')->count())->toBe(1);
});

test('markPhotoAsRemoved on an already-removed photo is a no-op — no duplicate log entry', function () {
    ['clinic' => $clinic, 'patient' => $patient] = setupPhotoOpsContext();
    $photo = PatientPhoto::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'drive_file_id' => 'x',
        'filename' => 'x.jpg', 'mime_type' => 'image/jpeg', 'status' => 'removed',
    ]);
    $service = app(GoogleDriveService::class);

    $service->markPhotoAsRemoved($photo);
    $service->markPhotoAsRemoved($photo);

    expect(DriveActivityLog::where('photo_id', $photo->id)->count())->toBe(0);
});

// ── syncPatientLibrary ───────────────────────────────────────────────────────

test('syncPatientLibrary short-circuits with zero HTTP calls when the clinic has no storage connection', function () {
    $plan = Plan::create([
        'name' => 'P', 'slug' => 'p-sync-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 1, 'max_users' => 1, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create(['name' => 'Sem Drive', 'slug' => 'sem-drive-' . uniqid(), 'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id]);
    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'X', 'sobrenome' => 'Y', 'status' => 'ativo']);

    $service = app(GoogleDriveService::class);
    $fake = wirePhotoOps($service);

    $result = $service->syncPatientLibrary($patient);

    expect($result)->toBe(['checked' => 0, 'removed' => 0, 'restored' => 0]);
    expect($fake->requestLog())->toBeEmpty();
});

test('syncPatientLibrary restores a previously-removed photo that reappeared on Drive, and marks a missing active photo as removed', function () {
    ['clinic' => $clinic, 'patient' => $patient] = setupPhotoOpsContext();
    $reappeared = PatientPhoto::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'drive_file_id' => 'reappeared-id',
        'filename' => 'a.jpg', 'mime_type' => 'image/jpeg', 'status' => 'removed',
    ]);
    $vanished = PatientPhoto::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'drive_file_id' => 'vanished-id',
        'filename' => 'b.jpg', 'mime_type' => 'image/jpeg', 'status' => 'active',
    ]);
    $stillThere = PatientPhoto::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'drive_file_id' => 'still-there-id',
        'filename' => 'c.jpg', 'mime_type' => 'image/jpeg', 'status' => 'active',
    ]);
    $service = app(GoogleDriveService::class);
    $fake = wirePhotoOps($service);
    // batchLookupFileIds só confirma quais IDs existem — reappeared e still-there, não vanished.
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), 'q='),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([
            GoogleDriveFakeHttp::driveFile('reappeared-id'),
            GoogleDriveFakeHttp::driveFile('still-there-id'),
        ]))
    );

    $result = $service->syncPatientLibrary($patient);

    expect($result)->toBe(['checked' => 3, 'removed' => 1, 'restored' => 1]);
    expect($reappeared->fresh()->status)->toBe('active')
        ->and($vanished->fresh()->status)->toBe('removed')
        ->and($stillThere->fresh()->status)->toBe('active');
});
