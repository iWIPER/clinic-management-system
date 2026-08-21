<?php

use App\Models\Clinic;
use App\Models\Plan;
use App\Models\SystemAdmin;
use App\Models\User;

// Fase System Admin/Backoffice — a regra fundamental de segurança desta
// fase inteira: /admin exige autenticação + privilégio global de System
// Admin, independente de role de clínica (owner/admin não bastam) e
// independente de current_clinic_id (testado explicitamente sem nenhuma
// clínica ativa na sessão).

function setupAdminAccessContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-adminaccess-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Admin Access', 'slug' => 'clinica-adminaccess-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);

    return compact('plan', 'clinic');
}

function makeClinicUser(Clinic $clinic, string $role, string $jobTitle = 'Dentista'): User
{
    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => $jobTitle]);
    $clinic->users()->attach($user->id, ['role' => $role]);

    return $user;
}

test('an unauthenticated request to /admin is redirected to login, not 403', function () {
    $this->get(route('admin.index'))->assertRedirect(route('login'));
});

test('a user with no system admin privilege and no clinic role at all gets 403', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('admin.index'))->assertForbidden();
});

test('a clinic owner without the system admin privilege gets 403', function () {
    ['clinic' => $clinic] = setupAdminAccessContext();
    $owner = makeClinicUser($clinic, 'owner');

    $this->actingAs($owner)->get(route('admin.index'))->assertForbidden();
});

test('a clinic admin without the system admin privilege gets 403', function () {
    ['clinic' => $clinic] = setupAdminAccessContext();
    $admin = makeClinicUser($clinic, 'admin');

    $this->actingAs($admin)->get(route('admin.index'))->assertForbidden();
});

test('a professional without the system admin privilege gets 403', function () {
    ['clinic' => $clinic] = setupAdminAccessContext();
    $professional = makeClinicUser($clinic, 'professional');

    $this->actingAs($professional)->get(route('admin.index'))->assertForbidden();
});

test('staff without the system admin privilege gets 403', function () {
    ['clinic' => $clinic] = setupAdminAccessContext();
    $staff = makeClinicUser($clinic, 'staff');

    $this->actingAs($staff)->get(route('admin.index'))->assertForbidden();
});

test('a user with the system admin privilege accesses /admin regardless of clinic role', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    $this->actingAs($admin)->get(route('admin.index'))->assertOk();
});

test('system admin access does not depend on current_clinic_id — works with zero clinics and no session clinic', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    expect($admin->clinics()->count())->toBe(0);

    $this->actingAs($admin)
        ->withSession([]) // explicitamente sem current_clinic_id
        ->get(route('admin.index'))
        ->assertOk();
});

test('system admin access is not affected by a stale/foreign current_clinic_id planted in session', function () {
    ['clinic' => $clinic] = setupAdminAccessContext();
    $admin = User::factory()->create(['email_verified_at' => now()]);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    $this->actingAs($admin)
        ->withSession(['current_clinic_id' => $clinic->id + 999999]) // clínica inexistente
        ->get(route('admin.index'))
        ->assertOk();
});

test('revoking system admin privilege immediately removes access', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $grant = SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    $this->actingAs($admin)->get(route('admin.index'))->assertOk();

    $grant->update(['revoked_at' => now()]);

    $this->actingAs($admin)->get(route('admin.index'))->assertForbidden();
});

test('every /admin/* route is protected, not just the dashboard', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('admin.clinics'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.users'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.system-admins'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.exports'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.logs'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.plans'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.referrals'))->assertForbidden();
});
