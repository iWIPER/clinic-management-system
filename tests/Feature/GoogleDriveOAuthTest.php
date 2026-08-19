<?php

use App\Exceptions\GoogleDriveReauthRequiredException;
use App\Models\Clinic;
use App\Models\ClinicStorageConnection;
use App\Models\Plan;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\FakeGoogleIdToken;
use Tests\Support\GoogleDriveFakeHttp;

// Fase C1.2.1 — testes diretos da lógica REAL de OAuth/cliente autenticado.
// Nenhuma chamada de rede real: o transporte HTTP do Google_Client interno
// é substituído via GoogleDriveService::useHttpClientForTesting() (seam
// mínima adicionada nesta fase), então toda a lógica de troca/renovação de
// token roda de verdade, só a rede é fake.

function setupOAuthContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-oauth-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);

    $clinic = Clinic::create([
        'name' => 'Clínica OAuth', 'slug' => 'clinica-oauth-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);

    return compact('plan', 'clinic');
}

function wireFakeHttp(GoogleDriveService $service): GoogleDriveFakeHttp
{
    $fake = new GoogleDriveFakeHttp();
    $service->useHttpClientForTesting($fake->client());

    return $fake;
}

// ── getAuthUrl / exchangeCode ──────────────────────────────────────────────

test('getAuthUrl builds a real Google consent URL with the configured client and offline access', function () {
    $service = app(GoogleDriveService::class);

    $url = $service->getAuthUrl();

    expect($url)->toContain('accounts.google.com')
        ->and($url)->toContain('access_type=offline')
        ->and($url)->toContain('prompt=consent')
        ->and($url)->toContain('response_type=code');
});

test('exchangeCode rejects an empty code before any network call', function () {
    $service = app(GoogleDriveService::class);
    $fake = wireFakeHttp($service);

    expect(fn () => $service->exchangeCode(''))->toThrow(InvalidArgumentException::class);
    expect($fake->requestLog())->toBeEmpty();
});

test('exchangeCode returns the real token payload from a successful code exchange', function () {
    $service = app(GoogleDriveService::class);
    $fake = wireFakeHttp($service);

    $token = GoogleDriveFakeHttp::tokenResponse(['access_token' => 'tok-abc', 'refresh_token' => 'ref-xyz']);
    $fake->onPath('POST', '/token', fn () => GoogleDriveFakeHttp::json($token));

    $result = $service->exchangeCode('valid-auth-code');

    expect($result['access_token'])->toBe('tok-abc')
        ->and($result['refresh_token'])->toBe('ref-xyz');
});

// CORRIGIDO em C1.2.1.1 (era um ClientException cru — ver histórico do
// arquivo). exchangeCode() agora passa por callOAuthEndpoint(), que traduz
// uma resposta HTTP de erro no formato OAuth do Google de volta pro array
// {"error": ...} que GoogleDriveController::callback() sempre esperou
// (linha 60: `if (isset($token['error']))`) — o redirect amigável
// "Erro na autenticação com Google" agora é alcançado de verdade.
test('exchangeCode returns a graceful error array on a Google API error response, matching what GoogleDriveController::callback() expects', function () {
    $service = app(GoogleDriveService::class);
    $fake = wireFakeHttp($service);

    $fake->onPath('POST', '/token', fn () => GoogleDriveFakeHttp::json([
        'error' => 'invalid_grant', 'error_description' => 'Malformed auth code.',
    ], 400));

    $result = $service->exchangeCode('bad-code');

    expect($result['error'])->toBe('invalid_grant')
        ->and($result['error_description'])->toBe('Malformed auth code.');
});

// Erro de rede de verdade (sem resposta HTTP nenhuma) NÃO é um erro OAuth do
// Google — não deve ser mascarado como um array de erro gracioso, porque não
// há nada de "erro na autenticação" pra mostrar; é uma falha de infra
// transitória. Continua subindo como exceção, como já acontecia.
test('exchangeCode still throws (does not swallow) a genuine network-level failure with no HTTP response at all', function () {
    $service = app(GoogleDriveService::class);
    $fake = wireFakeHttp($service);
    $fake->onPath('POST', '/token', fn () => throw new \GuzzleHttp\Exception\ConnectException(
        'Connection timed out', new \GuzzleHttp\Psr7\Request('POST', 'https://oauth2.googleapis.com/token')
    ));

    expect(fn () => $service->exchangeCode('some-code'))->toThrow(\GuzzleHttp\Exception\ConnectException::class);
});

// ── fetchEmailFromToken ─────────────────────────────────────────────────────

test('fetchEmailFromToken returns null when there is no id_token at all', function () {
    $service = app(GoogleDriveService::class);
    wireFakeHttp($service);

    expect($service->fetchEmailFromToken(['access_token' => 'x']))->toBeNull();
});

test('fetchEmailFromToken returns null for a malformed id_token', function () {
    $service = app(GoogleDriveService::class);
    $fake = wireFakeHttp($service);
    $fake->onPath('GET', '/certs', fn () => GoogleDriveFakeHttp::json(['keys' => []]));

    expect($service->fetchEmailFromToken(['id_token' => 'not-a-real-jwt']))->toBeNull();
});

test('fetchEmailFromToken extracts the real email from a genuinely signature-verified id_token', function () {
    $service = app(GoogleDriveService::class);
    $fake = wireFakeHttp($service);

    $clientId = config('services.google.client_id');
    $issued = FakeGoogleIdToken::issue('doutora@example.com', $clientId);

    $fake->onPath('GET', '/certs', fn () => GoogleDriveFakeHttp::json($issued['jwks']));

    $email = $service->fetchEmailFromToken(['id_token' => $issued['jwt']]);

    expect($email)->toBe('doutora@example.com');
});

// ── getDriveForClinic ───────────────────────────────────────────────────────

test('getDriveForClinic throws GoogleDriveReauthRequiredException when there is no connection at all', function () {
    ['clinic' => $clinic] = setupOAuthContext();
    $service = app(GoogleDriveService::class);
    wireFakeHttp($service);

    expect(fn () => $service->getDriveForClinic($clinic))->toThrow(GoogleDriveReauthRequiredException::class);
});

test('getDriveForClinic throws GoogleDriveReauthRequiredException when the connection has no refresh token', function () {
    ['clinic' => $clinic] = setupOAuthContext();
    ClinicStorageConnection::create([
        'clinic_id' => $clinic->id, 'provider' => 'google', 'status' => 'connected',
    ]);
    $service = app(GoogleDriveService::class);
    wireFakeHttp($service);

    expect(fn () => $service->getDriveForClinic($clinic))->toThrow(GoogleDriveReauthRequiredException::class);
});

test('getDriveForClinic reuses a cached, non-expired access token without hitting the token endpoint', function () {
    ['clinic' => $clinic] = setupOAuthContext();
    $cachedToken = GoogleDriveFakeHttp::tokenResponse(['created' => time()]);
    ClinicStorageConnection::create([
        'clinic_id' => $clinic->id, 'provider' => 'google', 'status' => 'connected',
        'refresh_token' => Crypt::encryptString('real-refresh-token'),
        'access_token'  => Crypt::encryptString(json_encode($cachedToken)),
        'expires_at'    => now()->addHour(),
    ]);
    $service = app(GoogleDriveService::class);
    $fake = wireFakeHttp($service);

    $drive = $service->getDriveForClinic($clinic);

    expect($drive)->toBeInstanceOf(\Google_Service_Drive::class);
    expect($fake->countRequestsToPath('/token'))->toBe(0);
});

test('getDriveForClinic refreshes proactively when the cached token is expired, and persists the new one', function () {
    ['clinic' => $clinic] = setupOAuthContext();
    $connection = ClinicStorageConnection::create([
        'clinic_id' => $clinic->id, 'provider' => 'google', 'status' => 'connected',
        'refresh_token' => Crypt::encryptString('real-refresh-token'),
        'access_token'  => Crypt::encryptString(json_encode(GoogleDriveFakeHttp::tokenResponse(['created' => time() - 7200]))),
        'expires_at'    => now()->subMinute(),
    ]);
    $service = app(GoogleDriveService::class);
    $fake = wireFakeHttp($service);
    $fake->onPath('POST', '/token', fn () => GoogleDriveFakeHttp::json(
        GoogleDriveFakeHttp::tokenResponse(['access_token' => 'refreshed-token-123'])
    ));

    $service->getDriveForClinic($clinic);

    expect($fake->countRequestsToPath('/token'))->toBe(1);

    $connection->refresh();
    $decoded = json_decode(Crypt::decryptString($connection->access_token), true);
    expect($decoded['access_token'])->toBe('refreshed-token-123')
        ->and($connection->expires_at->isFuture())->toBeTrue();
});

test('getDriveForClinic recovers from a corrupted cached access token by refreshing instead of failing', function () {
    ['clinic' => $clinic] = setupOAuthContext();
    ClinicStorageConnection::create([
        'clinic_id' => $clinic->id, 'provider' => 'google', 'status' => 'connected',
        'refresh_token' => Crypt::encryptString('real-refresh-token'),
        'access_token'  => 'this-is-not-valid-encrypted-data',
        'expires_at'    => now()->addHour(),
    ]);
    $service = app(GoogleDriveService::class);
    $fake = wireFakeHttp($service);
    $fake->onPath('POST', '/token', fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::tokenResponse()));

    $service->getDriveForClinic($clinic);

    expect($fake->countRequestsToPath('/token'))->toBe(1);
});

// CORRIGIDO em C1.2.1.1 — forceRefreshAccessToken() agora passa a chamada de
// refresh por callOAuthEndpoint(), que traduz a resposta HTTP 400 de
// invalid_grant de volta pro array {"error": "invalid_grant", ...} que o
// branch dedicado (clearInvalidTokens + GoogleDriveReauthRequiredException)
// sempre esperou. Esse branch agora é alcançado de verdade.
test('getDriveForClinic reaches the intended clearInvalidTokens branch and throws GoogleDriveReauthRequiredException on an invalid_grant refresh failure', function () {
    ['clinic' => $clinic] = setupOAuthContext();
    $connection = ClinicStorageConnection::create([
        'clinic_id' => $clinic->id, 'provider' => 'google', 'status' => 'connected',
        'refresh_token' => Crypt::encryptString('dead-refresh-token'),
        'access_token'  => Crypt::encryptString(json_encode(GoogleDriveFakeHttp::tokenResponse(['created' => time() - 7200]))),
        'expires_at'    => now()->subMinute(),
    ]);
    $service = app(GoogleDriveService::class);
    $fake = wireFakeHttp($service);
    $fake->onPath('POST', '/token', fn () => GoogleDriveFakeHttp::json([
        'error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.',
    ], 400));

    expect(fn () => $service->getDriveForClinic($clinic))->toThrow(GoogleDriveReauthRequiredException::class);

    $connection->refresh();
    expect($connection->status)->toBe('reauth_required')
        ->and($connection->access_token)->toBeNull()
        ->and($connection->refresh_token)->toBeNull();
});

// Item C do pedido: um 5xx (com corpo de erro OAuth reconhecível) NÃO deve
// virar reauth_required automaticamente — só invalid_grant/invalid_client
// fazem isso. Um 5xx cai no branch genérico (RuntimeException), preservando
// os tokens intactos pra tentar de novo depois.
test('getDriveForClinic on a 5xx with a recognizable OAuth error body throws RuntimeException, WITHOUT marking reauth_required', function () {
    ['clinic' => $clinic] = setupOAuthContext();
    $connection = ClinicStorageConnection::create([
        'clinic_id' => $clinic->id, 'provider' => 'google', 'status' => 'connected',
        'refresh_token' => Crypt::encryptString('real-refresh-token'),
        'access_token'  => Crypt::encryptString(json_encode(GoogleDriveFakeHttp::tokenResponse(['created' => time() - 7200]))),
        'expires_at'    => now()->subMinute(),
    ]);
    $service = app(GoogleDriveService::class);
    $fake = wireFakeHttp($service);
    $fake->onPath('POST', '/token', fn () => GoogleDriveFakeHttp::json([
        'error' => 'server_error', 'error_description' => 'Google está indisponível.',
    ], 500));

    expect(fn () => $service->getDriveForClinic($clinic))->toThrow(RuntimeException::class);

    $connection->refresh();
    expect($connection->status)->toBe('connected')
        ->and($connection->refresh_token)->not->toBeNull();
});

// Item D do pedido: uma falha transitória de verdade (timeout/conexão) não
// tem corpo de erro OAuth nenhum pra traduzir — não pode virar
// reauth_required (o refresh_token pode estar perfeitamente válido, o
// problema é a rede). A exceção de rede sobe crua, tokens intactos.
test('getDriveForClinic on a genuine network timeout during refresh propagates the raw network exception, WITHOUT marking reauth_required', function () {
    ['clinic' => $clinic] = setupOAuthContext();
    $connection = ClinicStorageConnection::create([
        'clinic_id' => $clinic->id, 'provider' => 'google', 'status' => 'connected',
        'refresh_token' => Crypt::encryptString('real-refresh-token'),
        'access_token'  => Crypt::encryptString(json_encode(GoogleDriveFakeHttp::tokenResponse(['created' => time() - 7200]))),
        'expires_at'    => now()->subMinute(),
    ]);
    $service = app(GoogleDriveService::class);
    $fake = wireFakeHttp($service);
    $fake->onPath('POST', '/token', fn () => throw new \GuzzleHttp\Exception\ConnectException(
        'Connection timed out', new \GuzzleHttp\Psr7\Request('POST', 'https://oauth2.googleapis.com/token')
    ));

    expect(fn () => $service->getDriveForClinic($clinic))->toThrow(\GuzzleHttp\Exception\ConnectException::class);

    $connection->refresh();
    expect($connection->status)->toBe('connected')
        ->and($connection->refresh_token)->not->toBeNull();
});

// ── tryRenewConnection ──────────────────────────────────────────────────────

test('tryRenewConnection returns false without any network call when there is no connection', function () {
    ['clinic' => $clinic] = setupOAuthContext();
    $service = app(GoogleDriveService::class);
    $fake = wireFakeHttp($service);

    expect($service->tryRenewConnection($clinic))->toBeFalse();
    expect($fake->requestLog())->toBeEmpty();
});

test('tryRenewConnection returns true and persists a fresh token on success', function () {
    ['clinic' => $clinic] = setupOAuthContext();
    $connection = ClinicStorageConnection::create([
        'clinic_id' => $clinic->id, 'provider' => 'google', 'status' => 'connected',
        'refresh_token' => Crypt::encryptString('real-refresh-token'),
    ]);
    $service = app(GoogleDriveService::class);
    $fake = wireFakeHttp($service);
    $fake->onPath('POST', '/token', fn () => GoogleDriveFakeHttp::json(
        GoogleDriveFakeHttp::tokenResponse(['access_token' => 'renewed-abc'])
    ));

    expect($service->tryRenewConnection($clinic))->toBeTrue();

    $connection->refresh();
    $decoded = json_decode(Crypt::decryptString($connection->access_token), true);
    expect($decoded['access_token'])->toBe('renewed-abc');
});

test('tryRenewConnection returns false (never throws) when Google rejects the refresh token', function () {
    ['clinic' => $clinic] = setupOAuthContext();
    ClinicStorageConnection::create([
        'clinic_id' => $clinic->id, 'provider' => 'google', 'status' => 'connected',
        'refresh_token' => Crypt::encryptString('dead-refresh-token'),
    ]);
    $service = app(GoogleDriveService::class);
    $fake = wireFakeHttp($service);
    $fake->onPath('POST', '/token', fn () => GoogleDriveFakeHttp::json([
        'error' => 'invalid_grant', 'error_description' => 'revoked',
    ], 400));

    expect($service->tryRenewConnection($clinic))->toBeFalse();
});
