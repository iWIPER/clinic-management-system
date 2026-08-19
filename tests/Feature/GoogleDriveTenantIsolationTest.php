<?php

use App\Models\Clinic;
use App\Models\ClinicStorageConnection;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\GoogleDriveFakeHttp;

// Fase C1.2.1, seção 12 — CRÍTICO. getDriveForClinic() é chamado de novo a
// cada operação real (não há client cacheado entre requisições), mas dentro
// de UMA MESMA instância de service (ex.: GoogleDriveHealthCheckService,
// injetado uma vez por request, chama vários métodos do GoogleDriveService
// em sequência) o estado interno ($this->client, $this->currentClinic) é
// mutável e compartilhado. Os testes abaixo provam que trocar de clínica no
// meio da vida de uma mesma instância nunca mistura token/pasta de uma
// clínica com o contexto da outra.

function setupTenantClinic(string $suffix, ?string $rootFolderId = null): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-tenant' . $suffix . '-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Tenant' . $suffix, 'slug' => 'clinica-tenant' . $suffix . '-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $connection = ClinicStorageConnection::create([
        'clinic_id' => $clinic->id, 'provider' => 'google', 'status' => 'connected',
        'refresh_token' => Crypt::encryptString('refresh-token' . $suffix),
        'drive_root_folder_id' => $rootFolderId,
    ]);
    $doctor = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($doctor->id, ['role' => 'professional']);
    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Paciente' . $suffix, 'sobrenome' => 'X', 'status' => 'ativo']);

    return compact('clinic', 'connection', 'doctor', 'patient');
}

test('refreshing an expired token for Clinic B never touches or updates Clinic A\'s connection row', function () {
    ['clinic' => $clinicA, 'connection' => $connA] = setupTenantClinic('A');
    ['clinic' => $clinicB, 'connection' => $connB] = setupTenantClinic('B');

    // A tem um token válido em cache (não deve disparar refresh nenhum).
    $connA->update([
        'access_token' => Crypt::encryptString(json_encode(GoogleDriveFakeHttp::tokenResponse(['created' => time()]))),
        'expires_at'   => now()->addHour(),
    ]);
    // B tem um token expirado (deve disparar refresh).
    $connB->update([
        'access_token' => Crypt::encryptString(json_encode(GoogleDriveFakeHttp::tokenResponse(['created' => time() - 7200]))),
        'expires_at'   => now()->subMinute(),
    ]);

    $service = app(GoogleDriveService::class); // MESMA instância pra ambas as clínicas.
    $fake = new GoogleDriveFakeHttp();
    $service->useHttpClientForTesting($fake->client());
    $fake->onPath('POST', '/token', fn () => GoogleDriveFakeHttp::json(
        GoogleDriveFakeHttp::tokenResponse(['access_token' => 'refreshed-for-B'])
    ));

    // Ordem importa: A primeiro (não deveria refrescar), B depois (deveria).
    $service->getDriveForClinic($clinicA);
    expect($fake->countRequestsToPath('/token'))->toBe(0);

    $service->getDriveForClinic($clinicB);
    expect($fake->countRequestsToPath('/token'))->toBe(1);

    // A permanece exatamente como estava — nunca tocado pelo refresh de B.
    $connA->refresh();
    $decodedA = json_decode(Crypt::decryptString($connA->access_token), true);
    expect($decodedA['access_token'])->not->toBe('refreshed-for-B');

    $connB->refresh();
    $decodedB = json_decode(Crypt::decryptString($connB->access_token), true);
    expect($decodedB['access_token'])->toBe('refreshed-for-B');
});

test('a refresh-token failure for Clinic B (invalid_grant path, once reached) never marks Clinic A as reauth_required', function () {
    ['clinic' => $clinicA, 'connection' => $connA] = setupTenantClinic('A');
    ['clinic' => $clinicB, 'connection' => $connB] = setupTenantClinic('B');

    $connA->update([
        'access_token' => Crypt::encryptString(json_encode(GoogleDriveFakeHttp::tokenResponse(['created' => time()]))),
        'expires_at'   => now()->addHour(),
    ]);
    $connB->update([
        'access_token' => Crypt::encryptString(json_encode(GoogleDriveFakeHttp::tokenResponse(['created' => time() - 7200]))),
        'expires_at'   => now()->subMinute(),
    ]);

    $service = app(GoogleDriveService::class);
    $fake = new GoogleDriveFakeHttp();
    $service->useHttpClientForTesting($fake->client());
    $fake->onPath('POST', '/token', fn () => GoogleDriveFakeHttp::googleError(400, 'invalid_grant'));

    $service->getDriveForClinic($clinicA);

    try {
        $service->getDriveForClinic($clinicB);
    } catch (\Throwable) {
        // Esperado — Guzzle lança em vez do fluxo pretendido (ver achado em
        // GoogleDriveOAuthTest); irrelevante pra este teste, que só verifica
        // isolamento entre clínicas.
    }

    $connA->refresh();
    expect($connA->status)->toBe('connected')
        ->and($connA->refresh_token)->not->toBeNull();
});

test('resolveUploadFolder for Clinic B never resolves or reuses Clinic A\'s cached root/doctor/patient folder ids', function () {
    ['clinic' => $clinicA, 'connection' => $connA, 'doctor' => $doctorA, 'patient' => $patientA] = setupTenantClinic('A', 'root-A');
    ['clinic' => $clinicB, 'connection' => $connB, 'doctor' => $doctorB, 'patient' => $patientB] = setupTenantClinic('B', 'root-B');

    foreach ([$connA, $connB] as $conn) {
        $conn->update([
            'access_token' => Crypt::encryptString(json_encode(GoogleDriveFakeHttp::tokenResponse())),
            'expires_at'   => now()->addHour(),
        ]);
    }
    $doctorA->clinics()->updateExistingPivot($clinicA->id, ['drive_doctor_folder_id' => 'doctor-A']);
    $doctorB->clinics()->updateExistingPivot($clinicB->id, ['drive_doctor_folder_id' => 'doctor-B']);
    $patientA->update(['drive_folder_id' => 'patientfolder-A']);
    $patientB->update(['drive_folder_id' => 'patientfolder-B']);

    $service = app(GoogleDriveService::class);
    $fake = new GoogleDriveFakeHttp();
    $service->useHttpClientForTesting($fake->client());
    // Cada checagem de existência responde com base no próprio id pedido —
    // se o código de B acidentalmente checasse um id de A, isso pegaria.
    foreach (['root-A', 'doctor-A', 'patientfolder-A', 'root-B', 'doctor-B', 'patientfolder-B'] as $id) {
        $fake->onPath('GET', "/files/{$id}", fn () => GoogleDriveFakeHttp::json(['id' => $id, 'trashed' => false]));
    }
    $categoryLookups = [];
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), 'q='),
        function ($r) use (&$categoryLookups) {
            $categoryLookups[] = urldecode((string) $r->getUri());
            return GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([GoogleDriveFakeHttp::driveFile('cat-id')]));
        }
    );

    $resultA = $service->resolveUploadFolder($patientA, $doctorA, 'Radiografias');
    $resultB = $service->resolveUploadFolder($patientB, $doctorB, 'Radiografias');

    expect($resultA['patient_folder_id'])->toBe('patientfolder-A')
        ->and($resultB['patient_folder_id'])->toBe('patientfolder-B');

    // A checagem de categoria de B nunca usa a pasta do paciente de A como parent.
    $bLookup = collect($categoryLookups)->last();
    expect($bLookup)->toContain('patientfolder-B')->not->toContain('patientfolder-A');
});
