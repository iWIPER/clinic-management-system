<?php

use App\Exceptions\DriveStructureMissingException;
use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Http\UploadedFile;

test('upload triggers disaster recovery modal when drive structure is missing', function () {
    ['user' => $user, 'patient' => $patient] = setupDriveUploadContext();

    $mock = Mockery::mock(GoogleDriveService::class);
    $mock->shouldReceive('uploadPhoto')
        ->once()
        ->andThrow(new DriveStructureMissingException('root', 'old-folder-id'));

    $this->app->instance(GoogleDriveService::class, $mock);

    $file = UploadedFile::fake()->image('foto.jpg');

    $this->actingAs($user)
        ->post(route('patients.photos.upload', $patient), [
            'photo' => $file,
            'categoria' => 'Fotografias Clínicas',
            'subcategoria' => 'Foto Inicial',
        ])
        ->assertRedirect()
        ->assertSessionHas('disaster_recovery_required', true)
        ->assertSessionMissing('error');
});

test('upload shows recovery success message after authorized structure recreation', function () {
    ['user' => $user, 'patient' => $patient] = setupDriveUploadContext();

    $photo = PatientPhoto::make([
        'clinic_id' => $patient->clinic_id,
        'patient_id' => $patient->id,
        'drive_file_id' => 'file-123',
        'drive_folder_id' => 'folder-456',
        'filename' => 'foto.jpg',
        'mime_type' => 'image/jpeg',
        'status' => 'active',
    ]);

    $mock = Mockery::mock(GoogleDriveService::class);
    $mock->shouldReceive('uploadPhoto')
        ->once()
        ->withArgs(fn ($p, $d, $path, $name, $mime, $meta, $authorized) => $authorized === true)
        ->andReturn([
            'photo' => $photo,
            'structure_recreated' => true,
        ]);

    $this->app->instance(GoogleDriveService::class, $mock);

    $file = UploadedFile::fake()->image('foto.jpg');

    $this->actingAs($user)
        ->post(route('patients.photos.upload', $patient), [
            'photo' => $file,
            'categoria' => 'Fotografias Clínicas',
            'subcategoria' => 'Foto Inicial',
            'authorize_structure_recovery' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas(
            'success',
            '✓ Estrutura recriada com sucesso. Uma nova estrutura foi criada no Google Drive. O upload foi concluído normalmente.'
        );
});

test('upload shows standard success message when drive structure already exists', function () {
    ['user' => $user, 'patient' => $patient] = setupDriveUploadContext();

    $photo = PatientPhoto::make([
        'clinic_id' => $patient->clinic_id,
        'patient_id' => $patient->id,
        'drive_file_id' => 'file-123',
        'drive_folder_id' => 'folder-456',
        'filename' => 'foto.jpg',
        'mime_type' => 'image/jpeg',
        'status' => 'active',
    ]);

    $mock = Mockery::mock(GoogleDriveService::class);
    $mock->shouldReceive('uploadPhoto')
        ->once()
        ->andReturn([
            'photo' => $photo,
            'structure_recreated' => false,
        ]);

    $this->app->instance(GoogleDriveService::class, $mock);

    $file = UploadedFile::fake()->image('foto.jpg');

    $this->actingAs($user)
        ->post(route('patients.photos.upload', $patient), [
            'photo' => $file,
            'categoria' => 'Fotografias Clínicas',
            'subcategoria' => 'Foto Inicial',
        ])
        ->assertRedirect()
        ->assertSessionHas(
            'success',
            'Foto de João Silva enviada para o Google Drive da clínica.'
        );
});

test('upload never exposes technical errors to the user', function () {
    ['user' => $user, 'patient' => $patient] = setupDriveUploadContext();

    $mock = Mockery::mock(GoogleDriveService::class);
    $mock->shouldReceive('uploadPhoto')
        ->once()
        ->andThrow(new \RuntimeException('404 File not found'));

    $this->app->instance(GoogleDriveService::class, $mock);

    $file = UploadedFile::fake()->image('foto.jpg');

    $this->actingAs($user)
        ->post(route('patients.photos.upload', $patient), [
            'photo' => $file,
            'categoria' => 'Fotografias Clínicas',
            'subcategoria' => 'Foto Inicial',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Não foi possível enviar o arquivo. Tente novamente em instantes.');
});