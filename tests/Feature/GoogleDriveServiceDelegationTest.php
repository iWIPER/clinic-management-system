<?php

use App\Exceptions\GoogleDriveReauthRequiredException;
use App\Models\Clinic;
use App\Services\GoogleDriveAuthService;
use App\Services\GoogleDriveCallExecutor;
use App\Services\GoogleDriveService;
use App\Services\GoogleDriveStructureService;

// Fase C1.2.2 — prova de que GoogleDriveService realmente delega OAuth pra
// GoogleDriveAuthService (não reimplementa nada), mockando o service de auth
// e verificando que o wrapper repassa argumento e retorno exatamente. Não
// duplica os ~19 cenários já cobertos em GoogleDriveOAuthTest — só prova a
// ligação entre as duas classes.
//
// Fase C1.2.3 — GoogleDriveService passou a depender também de
// GoogleDriveCallExecutor e GoogleDriveStructureService; nenhum dos
// cenários OAuth abaixo os usa, então entram resolvidos normalmente pelo
// container (via app()) só pra satisfazer o construtor.

function serviceWithMockedAuth(): array
{
    $mockAuth = Mockery::mock(GoogleDriveAuthService::class);
    $service = new GoogleDriveService(
        $mockAuth,
        app(GoogleDriveCallExecutor::class),
        app(GoogleDriveStructureService::class),
    );

    return [$service, $mockAuth];
}

test('getAuthUrl delegates to GoogleDriveAuthService and returns its exact result', function () {
    [$service, $mockAuth] = serviceWithMockedAuth();
    $mockAuth->shouldReceive('getAuthUrl')->once()->andReturn('https://accounts.google.com/fake-url');

    expect($service->getAuthUrl())->toBe('https://accounts.google.com/fake-url');
});

test('exchangeCode delegates the exact code and returns the exact result', function () {
    [$service, $mockAuth] = serviceWithMockedAuth();
    $mockAuth->shouldReceive('exchangeCode')->once()->with('the-auth-code')->andReturn(['access_token' => 'tok-123']);

    expect($service->exchangeCode('the-auth-code'))->toBe(['access_token' => 'tok-123']);
});

test('fetchEmailFromToken delegates the exact token array and returns the exact result', function () {
    [$service, $mockAuth] = serviceWithMockedAuth();
    $token = ['id_token' => 'abc'];
    $mockAuth->shouldReceive('fetchEmailFromToken')->once()->with($token)->andReturn('doutora@example.com');

    expect($service->fetchEmailFromToken($token))->toBe('doutora@example.com');
});

test('getDriveForClinic delegates the exact clinic and returns the exact result', function () {
    [$service, $mockAuth] = serviceWithMockedAuth();
    $clinic = new Clinic(['id' => 1]);
    $fakeDrive = Mockery::mock(Google_Service_Drive::class);
    $mockAuth->shouldReceive('getDriveForClinic')->once()->with($clinic)->andReturn($fakeDrive);

    expect($service->getDriveForClinic($clinic))->toBe($fakeDrive);
});

test('getDriveForClinic propagates GoogleDriveReauthRequiredException from the auth service unchanged', function () {
    [$service, $mockAuth] = serviceWithMockedAuth();
    $clinic = new Clinic(['id' => 1]);
    $mockAuth->shouldReceive('getDriveForClinic')->once()->with($clinic)
        ->andThrow(new GoogleDriveReauthRequiredException($clinic));

    expect(fn () => $service->getDriveForClinic($clinic))->toThrow(GoogleDriveReauthRequiredException::class);
});

test('tryRenewConnection delegates the exact clinic and returns the exact result', function () {
    [$service, $mockAuth] = serviceWithMockedAuth();
    $clinic = new Clinic(['id' => 1]);
    $mockAuth->shouldReceive('tryRenewConnection')->once()->with($clinic)->andReturn(true);

    expect($service->tryRenewConnection($clinic))->toBeTrue();
});

test('useHttpClientForTesting delegates the exact http client instance', function () {
    [$service, $mockAuth] = serviceWithMockedAuth();
    $http = new \GuzzleHttp\Client();
    $mockAuth->shouldReceive('useHttpClientForTesting')->once()->with($http);

    $service->useHttpClientForTesting($http);
});
