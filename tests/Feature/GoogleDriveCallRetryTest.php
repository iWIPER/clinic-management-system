<?php

use App\Models\Clinic;
use App\Models\ClinicStorageConnection;
use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Models\Plan;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\GoogleDriveFakeHttp;

// Fase C1.2.1 — callDrive() é a peça mais crítica (retry transparente em
// erro de autenticação). Testada aqui através de streamPhoto(), que não
// engole nenhuma exceção — deixa o comportamento real de propagação visível,
// ao contrário de métodos como getStorageQuota()/syncPatientLibrary() que
// capturam \Throwable e mascarariam o resultado.

function setupCallDriveContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-calldrive-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica CallDrive', 'slug' => 'clinica-calldrive-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    ClinicStorageConnection::create([
        'clinic_id' => $clinic->id, 'provider' => 'google', 'status' => 'connected',
        'refresh_token' => Crypt::encryptString('real-refresh-token'),
        'access_token'  => Crypt::encryptString(json_encode(GoogleDriveFakeHttp::tokenResponse())),
        'expires_at'    => now()->addHour(),
    ]);
    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'P', 'sobrenome' => 'X', 'status' => 'ativo']);
    $photo = PatientPhoto::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id,
        'drive_file_id' => 'file-123', 'filename' => 'foto.jpg', 'mime_type' => 'image/jpeg', 'status' => 'active',
    ]);

    return compact('clinic', 'patient', 'photo');
}

function wireFake(GoogleDriveService $service): GoogleDriveFakeHttp
{
    $fake = new GoogleDriveFakeHttp();
    $service->useHttpClientForTesting($fake->client());

    return $fake;
}

test('a successful Drive call goes through exactly once, no refresh attempted', function () {
    ['photo' => $photo] = setupCallDriveContext();
    $service = app(GoogleDriveService::class);
    $fake = wireFake($service);
    $fake->onPath('GET', '/files/file-123', fn () => GoogleDriveFakeHttp::raw('bytes', 200, ['Content-Type' => 'image/jpeg']));

    $response = $service->streamPhoto($photo);

    expect($response->getContent())->toBe('bytes')
        ->and($fake->countRequestsToPath('/files/file-123'))->toBe(1)
        ->and($fake->countRequestsToPath('/token'))->toBe(0);
});

test('a 401 auth error triggers exactly one token refresh, then exactly one retry that succeeds', function () {
    ['photo' => $photo] = setupCallDriveContext();
    $service = app(GoogleDriveService::class);
    $fake = wireFake($service);

    $attempt = 0;
    $fake->onPath('GET', '/files/file-123', function () use (&$attempt) {
        $attempt++;
        return $attempt === 1
            ? GoogleDriveFakeHttp::googleError(401, 'authError', 'Invalid Credentials')
            : GoogleDriveFakeHttp::raw('bytes-after-retry', 200, ['Content-Type' => 'image/jpeg']);
    });
    $fake->onPath('POST', '/token', fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::tokenResponse()));

    $response = $service->streamPhoto($photo);

    expect($response->getContent())->toBe('bytes-after-retry')
        ->and($fake->countRequestsToPath('/files/file-123'))->toBe(2)
        ->and($fake->countRequestsToPath('/token'))->toBe(1);
});

test('a non-auth error (404) is thrown immediately, with zero refresh and zero retry', function () {
    ['photo' => $photo] = setupCallDriveContext();
    $service = app(GoogleDriveService::class);
    $fake = wireFake($service);
    $fake->onPath('GET', '/files/file-123', fn () => GoogleDriveFakeHttp::googleError(404, 'notFound', 'File not found'));

    expect(fn () => $service->streamPhoto($photo))->toThrow(Google_Service_Exception::class);
    expect($fake->countRequestsToPath('/files/file-123'))->toBe(1)
        ->and($fake->countRequestsToPath('/token'))->toBe(0);
});

// CORRIGIDO em C1.2.1.1 — mesmo fix de callOAuthEndpoint() documentado em
// GoogleDriveOAuthTest: o refresh disparado pelo retry de callDrive() agora
// lança GoogleDriveReauthRequiredException de verdade quando o Google
// rejeita o refresh_token, em vez de uma exceção Guzzle crua.
test('when the retry-triggering refresh itself fails (invalid_grant), GoogleDriveReauthRequiredException propagates, as the code intends', function () {
    ['photo' => $photo] = setupCallDriveContext();
    $service = app(GoogleDriveService::class);
    $fake = wireFake($service);
    $fake->onPath('GET', '/files/file-123', fn () => GoogleDriveFakeHttp::googleError(401, 'authError', 'Invalid Credentials'));
    $fake->onPath('POST', '/token', fn () => GoogleDriveFakeHttp::json([
        'error' => 'invalid_grant', 'error_description' => 'revoked',
    ], 400));

    expect(fn () => $service->streamPhoto($photo))->toThrow(\App\Exceptions\GoogleDriveReauthRequiredException::class);
    // Só uma tentativa de leitura antes do refresh falhar — nunca uma segunda.
    expect($fake->countRequestsToPath('/files/file-123'))->toBe(1);
});

test('a 403 with reason=authError in the error body is also treated as an auth error (not just HTTP 401)', function () {
    ['photo' => $photo] = setupCallDriveContext();
    $service = app(GoogleDriveService::class);
    $fake = wireFake($service);

    $attempt = 0;
    $fake->onPath('GET', '/files/file-123', function () use (&$attempt) {
        $attempt++;
        return $attempt === 1
            ? GoogleDriveFakeHttp::googleError(403, 'authError', 'Auth error via 403')
            : GoogleDriveFakeHttp::raw('recovered', 200, ['Content-Type' => 'image/jpeg']);
    });
    $fake->onPath('POST', '/token', fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::tokenResponse()));

    $response = $service->streamPhoto($photo);
    expect($response->getContent())->toBe('recovered');
});

test('a 403 with a non-auth, non-rate-limit reason is thrown immediately by callDrive with no retry of its own', function () {
    ['photo' => $photo] = setupCallDriveContext();
    $service = app(GoogleDriveService::class);
    $fake = wireFake($service);
    $fake->onPath('GET', '/files/file-123', fn () => GoogleDriveFakeHttp::googleError(403, 'forbidden', 'Access denied'));

    expect(fn () => $service->streamPhoto($photo))->toThrow(Google_Service_Exception::class);
    expect($fake->countRequestsToPath('/files/file-123'))->toBe(1)
        ->and($fake->countRequestsToPath('/token'))->toBe(0);
});

// Verificação (não suposição): Google_Task_Runner TEM um retry_map interno
// mapeando rateLimitExceeded/500/503 pra retry automático (Task/Runner.php),
// mas Client.php usa 'retry' => [] por padrão — o runner só é acionado se
// isso for explicitamente habilitado. GoogleDriveService nunca faz isso, e
// comprovei aqui que rateLimitExceeded NÃO é retentado sozinho por baixo dos
// panos com a configuração padrão: exatamente 1 tentativa, igual a qualquer
// outro erro não-auth — tratado exatamente como 'forbidden' pra fins de
// callDrive(), que também não reage a ele.
test('rate-limit errors are NOT retried automatically with the default client config — same single-attempt behavior as any other non-auth error', function () {
    ['photo' => $photo] = setupCallDriveContext();
    $service = app(GoogleDriveService::class);
    $fake = wireFake($service);
    $fake->onPath('GET', '/files/file-123', fn () => GoogleDriveFakeHttp::googleError(403, 'rateLimitExceeded', 'Too many requests'));

    expect(fn () => $service->streamPhoto($photo))->toThrow(Google_Service_Exception::class);
    expect($fake->countRequestsToPath('/files/file-123'))->toBe(1)
        ->and($fake->countRequestsToPath('/token'))->toBe(0);
});
