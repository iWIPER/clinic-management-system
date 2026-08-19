<?php

use App\Models\AccessLog;
use App\Models\Clinic;
use App\Models\Plan;
use App\Models\SystemAdmin;
use App\Models\User;

function setupExportContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-export-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Export', 'slug' => 'clinica-export-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    return compact('plan', 'clinic', 'admin');
}

test('index page lists the available datasets', function () {
    ['admin' => $admin] = setupExportContext();

    $this->actingAs($admin)
        ->get(route('admin.exports'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Exports/Index')->has('datasets'));
});

test('a normal user cannot reach any export endpoint', function () {
    $normal = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($normal)->get(route('admin.exports'))->assertForbidden();
    $this->actingAs($normal)->post(route('admin.exports.download', 'clinics'))->assertForbidden();
});

test('downloading an unknown dataset 404s instead of leaking an arbitrary table', function () {
    ['admin' => $admin] = setupExportContext();

    $this->actingAs($admin)
        ->post(route('admin.exports.download', 'nao_existe'))
        ->assertNotFound();
});

test('clinics dataset streams a real CSV containing the seeded clinic', function () {
    ['clinic' => $clinic, 'admin' => $admin] = setupExportContext();

    $response = $this->actingAs($admin)->post(route('admin.exports.download', 'clinics'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    // fputcsv cita campos com espaço no nome ("Nome Fantasia") — comportamento
    // correto do próprio fputcsv, não um bug; o teste original esperava a
    // string errada (sem aspas).
    expect($content)->toContain($clinic->name)
        ->and($content)->toContain('ID;Nome;"Nome Fantasia"');
});

test('status filter is actually applied to the exported dataset', function () {
    ['clinic' => $clinicA, 'admin' => $admin] = setupExportContext();
    $plan = \App\Models\Plan::create([
        'name' => 'Test Plan B', 'slug' => 'test-plan-export-b-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinicB = Clinic::create([
        'name' => 'Clínica Suspensa Export', 'slug' => 'clinica-suspensa-export-' . uniqid(),
        'type' => 'odontologia', 'status' => 'suspended', 'plan_id' => $plan->id,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.exports.download', 'clinics'), ['status' => 'suspended']);
    $content  = $response->streamedContent();

    expect($content)->toContain($clinicB->name)
        ->and($content)->not->toContain($clinicA->name);
});

test('every export downloaded is audited with dataset and filters', function () {
    ['admin' => $admin] = setupExportContext();

    $this->actingAs($admin)->post(route('admin.exports.download', 'users'), ['status' => 'ativo']);

    $log = AccessLog::where('action', 'admin_export_downloaded')->latest('created_at')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->metadata['dataset'])->toBe('users')
        ->and($log->metadata['filters']['status'])->toBe('ativo');
});

test('patients are never among the exportable datasets — deliberate data-minimization decision', function () {
    expect(array_key_exists('patients', \App\Services\Admin\ExportService::DATASETS))->toBeFalse();
});
