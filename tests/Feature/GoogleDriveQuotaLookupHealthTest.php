<?php

use App\Models\Clinic;
use App\Models\ClinicStorageConnection;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\GoogleDriveFakeHttp;

function setupQuotaContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-quota-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Quota', 'slug' => 'clinica-quota-' . uniqid(),
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
    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'X', 'sobrenome' => 'Y', 'status' => 'ativo', 'drive_folder_id' => 'patient-folder']);

    return compact('clinic', 'doctor', 'patient');
}

function wireQuota(GoogleDriveService $service): GoogleDriveFakeHttp
{
    $fake = new GoogleDriveFakeHttp();
    $service->useHttpClientForTesting($fake->client());

    return $fake;
}

// ── getStorageQuota ──────────────────────────────────────────────────────────

test('getStorageQuota computes limit/usage/available/percentage correctly from a real quota response', function () {
    ['clinic' => $clinic] = setupQuotaContext();
    $service = app(GoogleDriveService::class);
    $fake = wireQuota($service);
    $fake->onPath('GET', '/about', fn () => GoogleDriveFakeHttp::json([
        'storageQuota' => ['limit' => '1000000000', 'usage' => '250000000'],
    ]));

    $quota = $service->getStorageQuota($clinic);

    expect($quota['limit_bytes'])->toBe(1000000000)
        ->and($quota['usage_bytes'])->toBe(250000000)
        ->and($quota['available_bytes'])->toBe(750000000)
        ->and($quota['percentage'])->toBe(25.0);
});

test('getStorageQuota returns null for an unlimited (Workspace) account with no limit', function () {
    ['clinic' => $clinic] = setupQuotaContext();
    $service = app(GoogleDriveService::class);
    $fake = wireQuota($service);
    $fake->onPath('GET', '/about', fn () => GoogleDriveFakeHttp::json(['storageQuota' => ['usage' => '5000']]));

    expect($service->getStorageQuota($clinic))->toBeNull();
});

test('getStorageQuota returns null (never throws) on any underlying failure', function () {
    ['clinic' => $clinic] = setupQuotaContext();
    $service = app(GoogleDriveService::class);
    $fake = wireQuota($service);
    $fake->onPath('GET', '/about', fn () => GoogleDriveFakeHttp::googleError(500, 'internalError'));

    expect($service->getStorageQuota($clinic))->toBeNull();
});

test('getStorageQuota returns null when the clinic has no Drive connection at all', function () {
    $plan = Plan::create([
        'name' => 'P', 'slug' => 'p-noconn-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 1, 'max_users' => 1, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create(['name' => 'Sem Conexão', 'slug' => 'sem-conexao-' . uniqid(), 'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id]);
    $service = app(GoogleDriveService::class);
    wireQuota($service);

    expect($service->getStorageQuota($clinic))->toBeNull();
});

// ── batchLookupFileIds ───────────────────────────────────────────────────────

test('batchLookupFileIds returns only the ids that actually exist, empty array short-circuits with zero calls', function () {
    ['clinic' => $clinic] = setupQuotaContext();
    $service = app(GoogleDriveService::class);
    $fake = wireQuota($service);
    $drive = $service->getDriveForClinic($clinic);

    expect($service->batchLookupFileIds($drive, []))->toBe([]);
    expect($fake->requestLog())->toBeEmpty();

    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), 'q='),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([
            GoogleDriveFakeHttp::driveFile('id-1'), GoogleDriveFakeHttp::driveFile('id-3'),
        ]))
    );

    $found = $service->batchLookupFileIds($drive, ['id-1', 'id-2', 'id-3']);

    expect($found)->toBe(['id-1', 'id-3']);
});

test('batchLookupFileIds chunks into batches of 50, making one call per chunk', function () {
    ['clinic' => $clinic] = setupQuotaContext();
    $service = app(GoogleDriveService::class);
    $fake = wireQuota($service);
    $drive = $service->getDriveForClinic($clinic);
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), 'q='),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([]))
    );

    $ids = array_map(fn ($i) => "id-{$i}", range(1, 120));
    $service->batchLookupFileIds($drive, $ids);

    // 120 ids / 50 por lote = 3 chamadas.
    expect($fake->countRequestsToPath('q='))->toBe(3);
});

test('batchLookupFileIds escapes a single quote inside a file id in the query string', function () {
    ['clinic' => $clinic] = setupQuotaContext();
    $service = app(GoogleDriveService::class);
    $fake = wireQuota($service);
    $drive = $service->getDriveForClinic($clinic);
    $captured = null;
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), 'q='),
        function ($r) use (&$captured) { $captured = urldecode((string) $r->getUri()); return GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([])); }
    );

    $service->batchLookupFileIds($drive, ["weird'id"]);

    expect($captured)->toContain("weird\\'id");
});

// ── probeDrivePermissions ────────────────────────────────────────────────────

test('probeDrivePermissions reports ok for every step on full success', function () {
    ['clinic' => $clinic] = setupQuotaContext();
    $service = app(GoogleDriveService::class);
    $fake = wireQuota($service);
    $drive = $service->getDriveForClinic($clinic);
    $fake->on(fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'), fn () => GoogleDriveFakeHttp::json(['id' => 'probe-file']));
    $fake->on(fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), '/files/probe-file'), fn () => GoogleDriveFakeHttp::json(['id' => 'probe-file', 'name' => 'probe-file']));
    $fake->on(fn ($r) => $r->getMethod() === 'PATCH' && str_contains((string) $r->getUri(), '/files/probe-file'), fn () => GoogleDriveFakeHttp::json(['id' => 'probe-file']));
    $fake->on(fn ($r) => $r->getMethod() === 'DELETE' && str_contains((string) $r->getUri(), '/files/probe-file'), fn () => GoogleDriveFakeHttp::raw('', 204));

    $results = $service->probeDrivePermissions('parent-folder', $drive);

    $byKey = collect($results)->keyBy('key');
    expect($byKey['write']['status'])->toBe('ok')
        ->and($byKey['read']['status'])->toBe('ok')
        ->and($byKey['rename']['status'])->toBe('ok')
        ->and($byKey['move']['status'])->toBe('ok')
        ->and($byKey['delete']['status'])->toBe('ok');
});

test('probeDrivePermissions returns the degraded all-fail shape when it cannot even create the probe file', function () {
    ['clinic' => $clinic] = setupQuotaContext();
    $service = app(GoogleDriveService::class);
    $fake = wireQuota($service);
    $drive = $service->getDriveForClinic($clinic);
    $fake->on(fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'), fn () => GoogleDriveFakeHttp::googleError(403, 'forbidden'));

    $results = $service->probeDrivePermissions('parent-folder', $drive);

    $byKey = collect($results)->keyBy('key');
    expect($byKey['read']['status'])->toBe('fail')
        ->and($byKey['write']['status'])->toBe('fail')
        ->and($byKey['move']['status'])->toBe('skipped')
        ->and($byKey['rename']['status'])->toBe('skipped')
        ->and($byKey['delete']['status'])->toBe('skipped');
});

// ACHADO (C1.2.1, não corrigido — comportamento real descoberto escrevendo
// este teste, não hipotético): em GoogleDriveService.php, o closure passado
// ao check('delete', ...) faz `use ($drive, $testFileId)` — POR VALOR, não
// por referência. `$testFileId = null;` dentro dele só zera a cópia local do
// closure; a variável $testFileId do método probeDrivePermissions() nunca é
// alterada. Resultado: o bloco de "cleanup" logo abaixo (`if ($testFileId) {
// ... delete de novo ... }`) SEMPRE executa quando o passo 'delete' teve
// sucesso — uma segunda chamada DELETE desnecessária pro MESMO arquivo (que
// já não existe mais), que dá 404 e é silenciosamente ignorada
// CORRIGIDO em C1.2.1.1 — a captura do closure de delete agora é por
// referência (&$testFileId), então "$testFileId = null" depois de um DELETE
// bem-sucedido realmente zera a variável externa, e o bloco de cleanup
// (`if ($testFileId) { ... }`) corretamente não dispara de novo.
test('probeDrivePermissions makes exactly ONE DELETE call on a successful delete step — no redundant cleanup call', function () {
    ['clinic' => $clinic] = setupQuotaContext();
    $service = app(GoogleDriveService::class);
    $fake = wireQuota($service);
    $drive = $service->getDriveForClinic($clinic);
    $fake->on(fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'), fn () => GoogleDriveFakeHttp::json(['id' => 'probe-file']));
    $fake->on(fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), '/files/probe-file'), fn () => GoogleDriveFakeHttp::json(['id' => 'probe-file']));
    $fake->on(fn ($r) => $r->getMethod() === 'PATCH' && str_contains((string) $r->getUri(), '/files/probe-file'), fn () => GoogleDriveFakeHttp::json(['id' => 'probe-file']));
    $deleteCalls = 0;
    $fake->on(fn ($r) => $r->getMethod() === 'DELETE' && str_contains((string) $r->getUri(), '/files/probe-file'), function () use (&$deleteCalls) {
        $deleteCalls++;
        return GoogleDriveFakeHttp::raw('', 204);
    });

    $results = $service->probeDrivePermissions('parent-folder', $drive);

    expect($deleteCalls)->toBe(1);
    expect(collect($results)->firstWhere('key', 'delete')['status'])->toBe('ok');
});

// Falha antes do create (não chega nem a tentar criar o arquivo de teste) —
// nenhuma chamada de delete de qualquer tipo deve acontecer.
test('probeDrivePermissions makes zero DELETE calls when it never even managed to create the probe file', function () {
    ['clinic' => $clinic] = setupQuotaContext();
    $service = app(GoogleDriveService::class);
    $fake = wireQuota($service);
    $drive = $service->getDriveForClinic($clinic);
    $fake->on(fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'), fn () => GoogleDriveFakeHttp::googleError(403, 'forbidden'));
    $deleteCalls = 0;
    $fake->on(fn ($r) => $r->getMethod() === 'DELETE', function () use (&$deleteCalls) { $deleteCalls++; return GoogleDriveFakeHttp::raw('', 204); });

    $service->probeDrivePermissions('parent-folder', $drive);

    expect($deleteCalls)->toBe(0);
});

// Passo "delete" falhou de verdade (ex.: sem permissão) — o cleanup tenta de
// novo, mas o arquivo já não existe mais (404) por qualquer outro motivo.
// Isso nunca deve virar um erro visível ao usuário — o catch(\Throwable)
// silencioso do cleanup precisa continuar funcionando.
test('a 404 during the cleanup-after-failed-delete attempt is silently tolerated, never surfacing as a user-facing error', function () {
    ['clinic' => $clinic] = setupQuotaContext();
    $service = app(GoogleDriveService::class);
    $fake = wireQuota($service);
    $drive = $service->getDriveForClinic($clinic);
    $fake->on(fn ($r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/files') && !str_contains((string) $r->getUri(), '/token'), fn () => GoogleDriveFakeHttp::json(['id' => 'probe-file']));
    $fake->on(fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), '/files/probe-file'), fn () => GoogleDriveFakeHttp::json(['id' => 'probe-file']));
    $fake->on(fn ($r) => $r->getMethod() === 'PATCH' && str_contains((string) $r->getUri(), '/files/probe-file'), fn () => GoogleDriveFakeHttp::json(['id' => 'probe-file']));
    $deleteCalls = 0;
    // O passo "delete" em si falha (403) — $testFileId permanece setado —
    // então o cleanup tenta de novo e recebe 404 (arquivo já sumiu por outra via).
    $fake->on(fn ($r) => $r->getMethod() === 'DELETE' && str_contains((string) $r->getUri(), '/files/probe-file'), function () use (&$deleteCalls) {
        $deleteCalls++;
        return GoogleDriveFakeHttp::googleError($deleteCalls === 1 ? 403 : 404, $deleteCalls === 1 ? 'forbidden' : 'notFound');
    });

    $results = $service->probeDrivePermissions('parent-folder', $drive);

    expect($deleteCalls)->toBe(2);
    expect(collect($results)->firstWhere('key', 'delete')['status'])->toBe('fail');
});

// ── structureWasPreviouslyEstablished ───────────────────────────────────────

test('structureWasPreviouslyEstablished is false when nothing was ever cached, true when any single level was', function () {
    ['clinic' => $clinic, 'doctor' => $doctor] = setupQuotaContext();
    $patientNoStructure = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'A', 'sobrenome' => 'B', 'status' => 'ativo']);
    $service = app(GoogleDriveService::class);

    expect($service->structureWasPreviouslyEstablished($patientNoStructure, $doctor))->toBeFalse();

    $patientNoStructure->update(['drive_folder_id' => 'has-one']);
    expect($service->structureWasPreviouslyEstablished($patientNoStructure->fresh(), $doctor))->toBeTrue();
});

// ── listAllPatientFiles ──────────────────────────────────────────────────────

test('listAllPatientFiles walks subfolders recursively and labels each file with its immediate folder name', function () {
    ['clinic' => $clinic] = setupQuotaContext();
    $service = app(GoogleDriveService::class);
    $fake = wireQuota($service);
    $drive = $service->getDriveForClinic($clinic);

    // Raiz do paciente: 1 arquivo direto + 1 subpasta "Radiografias".
    // mimeType != ... (listFilesInFolder) vs mimeType = ... (busca de subpastas) —
    // a presença de "%21%3D" (!=) urlencoded distingue as duas.
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), "%27patient-root%27%20in%20parents")
            && str_contains((string) $r->getUri(), '%21%3D'),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([
            ['id' => 'file-root-1', 'name' => 'termo.pdf', 'mimeType' => 'application/pdf'],
        ]))
    );
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), "%27patient-root%27%20in%20parents")
            && !str_contains((string) $r->getUri(), '%21%3D'),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([
            ['id' => 'subfolder-1', 'name' => 'Radiografias'],
        ]))
    );
    // Dentro de "Radiografias": 1 arquivo, nenhuma subpasta.
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), "%27subfolder-1%27%20in%20parents")
            && str_contains((string) $r->getUri(), '%21%3D'),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([
            ['id' => 'file-sub-1', 'name' => 'raio-x.jpg', 'mimeType' => 'image/jpeg'],
        ]))
    );
    $fake->on(
        fn ($r) => $r->getMethod() === 'GET' && str_contains((string) $r->getUri(), "%27subfolder-1%27%20in%20parents")
            && !str_contains((string) $r->getUri(), '%21%3D'),
        fn () => GoogleDriveFakeHttp::json(GoogleDriveFakeHttp::fileList([]))
    );

    $files = $service->listAllPatientFiles('patient-root', $drive);

    expect($files)->toHaveCount(2);
    $byId = collect($files)->keyBy('id');
    expect($byId['file-root-1']['folder'])->toBe('Paciente')
        ->and($byId['file-sub-1']['folder'])->toBe('Radiografias');
});
