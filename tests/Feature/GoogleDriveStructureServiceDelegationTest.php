<?php

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use App\Services\GoogleDriveAuthService;
use App\Services\GoogleDriveCallExecutor;
use App\Services\GoogleDriveService;
use App\Services\GoogleDriveStructureService;

// Fase C1.2.3 — prova de que GoogleDriveService realmente delega
// folder/recovery pra GoogleDriveStructureService (não reimplementa
// nada), mockando o service de estrutura e verificando que cada wrapper
// repassa argumento e retorno exatamente. Não duplica os cenários reais
// já cobertos em GoogleDriveFoldersAndRecoveryTest — só prova a ligação
// entre as duas classes.

function serviceWithMockedStructure(): array
{
    $mockAuth      = Mockery::mock(GoogleDriveAuthService::class);
    $mockStructure = Mockery::mock(GoogleDriveStructureService::class);
    $service = new GoogleDriveService(
        $mockAuth,
        app(GoogleDriveCallExecutor::class),
        $mockStructure,
    );

    return [$service, $mockAuth, $mockStructure];
}

test('structureWasPreviouslyEstablished delegates the exact patient/doctor and returns the exact result', function () {
    [$service, , $mockStructure] = serviceWithMockedStructure();
    $patient = new Patient(['id' => 1]);
    $doctor  = new User(['id' => 2]);
    $mockStructure->shouldReceive('structureWasPreviouslyEstablished')->once()->with($patient, $doctor)->andReturn(true);

    expect($service->structureWasPreviouslyEstablished($patient, $doctor))->toBeTrue();
});

test('resolveUploadFolder delegates the exact patient/doctor/categoria and returns the exact result', function () {
    [$service, , $mockStructure] = serviceWithMockedStructure();
    $patient  = new Patient(['id' => 1]);
    $doctor   = new User(['id' => 2]);
    $expected = ['upload_folder_id' => 'up-1', 'patient_folder_id' => 'pf-1'];
    $mockStructure->shouldReceive('resolveUploadFolder')->once()->with($patient, $doctor, 'Radiografias')->andReturn($expected);

    expect($service->resolveUploadFolder($patient, $doctor, 'Radiografias'))->toBe($expected);
});

test('recoverStructure delegates the exact patient/doctor', function () {
    [$service, , $mockStructure] = serviceWithMockedStructure();
    $patient = new Patient(['id' => 1]);
    $doctor  = new User(['id' => 2]);
    $mockStructure->shouldReceive('recoverStructure')->once()->with($patient, $doctor);

    $service->recoverStructure($patient, $doctor);
});

test('locateFolder delegates name/parentId/drive plus the current clinic set by the last getDriveForClinic() call', function () {
    [$service, $mockAuth, $mockStructure] = serviceWithMockedStructure();
    $clinic    = new Clinic(['id' => 5]);
    $fakeDrive = Mockery::mock(Google_Service_Drive::class);
    $mockAuth->shouldReceive('getDriveForClinic')->once()->with($clinic)->andReturn($fakeDrive);
    $service->getDriveForClinic($clinic);

    $mockStructure->shouldReceive('locateFolder')->once()->with('Categoria X', 'parent-1', $fakeDrive, $clinic)->andReturn('folder-abc');

    expect($service->locateFolder('Categoria X', 'parent-1', $fakeDrive))->toBe('folder-abc');
});

test('folderExists delegates folderId/drive plus the current clinic set by the last getDriveForClinic() call', function () {
    [$service, $mockAuth, $mockStructure] = serviceWithMockedStructure();
    $clinic    = new Clinic(['id' => 5]);
    $fakeDrive = Mockery::mock(Google_Service_Drive::class);
    $mockAuth->shouldReceive('getDriveForClinic')->once()->with($clinic)->andReturn($fakeDrive);
    $service->getDriveForClinic($clinic);

    $mockStructure->shouldReceive('folderExists')->once()->with('folder-1', $fakeDrive, $clinic)->andReturn(true);

    expect($service->folderExists('folder-1', $fakeDrive))->toBeTrue();
});
