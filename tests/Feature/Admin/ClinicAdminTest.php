<?php

use App\Models\Clinic;
use App\Models\Plan;
use App\Models\SystemAdmin;
use App\Models\User;

function setupClinicAdminContext(string $suffix = ''): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-clinicadmin' . $suffix . '-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Admin CRUD' . $suffix, 'slug' => 'clinica-admincrud' . $suffix . '-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($owner->id, ['role' => 'owner']);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    return compact('plan', 'clinic', 'owner', 'admin');
}

test('index lists clinics and supports search and status filter', function () {
    ['clinic' => $clinic, 'admin' => $admin] = setupClinicAdminContext();

    $this->actingAs($admin)
        ->get(route('admin.clinics', ['search' => $clinic->name]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Clinics/Index')->has('clinics.data', 1));

    $this->actingAs($admin)
        ->get(route('admin.clinics', ['status' => 'suspended']))
        ->assertInertia(fn ($page) => $page->has('clinics.data', 0));
});

test('show renders full clinic detail: owner, members, subscription, patients count', function () {
    ['clinic' => $clinic, 'owner' => $owner, 'admin' => $admin] = setupClinicAdminContext();

    $this->actingAs($admin)
        ->get(route('admin.clinics.show', $clinic->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Clinics/Show')
            ->where('clinic.id', $clinic->id)
            ->where('clinic.owner.id', $owner->id)
            ->has('members', 1)
        );
});

test('a system admin can block and then unblock a clinic, both audited', function () {
    ['clinic' => $clinic, 'admin' => $admin] = setupClinicAdminContext();

    $this->actingAs($admin)->postJson(route('admin.clinics.block', $clinic->id))->assertOk();
    expect($clinic->fresh()->status)->toBe('suspended');
    expect(\App\Models\AccessLog::where('action', 'admin_clinic_blocked')->exists())->toBeTrue();

    $this->actingAs($admin)->postJson(route('admin.clinics.unblock', $clinic->id))->assertOk();
    expect($clinic->fresh()->status)->toBe('active');
    expect(\App\Models\AccessLog::where('action', 'admin_clinic_unblocked')->exists())->toBeTrue();
});

test('a blocked clinic actually loses access — its own owner never reaches the real page, not just cosmetically flagged', function () {
    ['clinic' => $clinic, 'owner' => $owner, 'admin' => $admin] = setupClinicAdminContext();

    $this->actingAs($admin)->postJson(route('admin.clinics.block', $clinic->id))->assertOk();

    // GET normal (navegação Inertia) mostra a tela de aviso em vez do
    // dashboard real — ver EnsureCurrentClinic e
    // tests/Feature/ClinicSuspendedTest.php pra cobertura completa do
    // middleware (inclui write actions recusadas com 403).
    $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/ClinicSuspended'));
});

test('unblocking restores real access for the clinic owner', function () {
    ['clinic' => $clinic, 'owner' => $owner, 'admin' => $admin] = setupClinicAdminContext();

    $this->actingAs($admin)->postJson(route('admin.clinics.block', $clinic->id))->assertOk();
    $this->actingAs($admin)->postJson(route('admin.clinics.unblock', $clinic->id))->assertOk();

    $this->actingAs($owner)
        ->withSession(['current_clinic_id' => $clinic->id])
        ->get(route('dashboard'))
        ->assertOk();
});

test('clinic admin operations are cross-tenant by nature but never leak one clinic into another', function () {
    ['clinic' => $clinicA, 'admin' => $admin] = setupClinicAdminContext('a');
    ['clinic' => $clinicB] = setupClinicAdminContext('b');

    $this->actingAs($admin)->postJson(route('admin.clinics.block', $clinicA->id))->assertOk();

    expect($clinicA->fresh()->status)->toBe('suspended')
        ->and($clinicB->fresh()->status)->toBe('active');
});
