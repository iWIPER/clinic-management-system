<?php

use App\Models\Clinic;
use App\Models\Plan;
use App\Models\User;

function setupAccountActiveContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-accountactive-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Account Active', 'slug' => 'clinica-accountactive-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);

    return compact('plan', 'clinic');
}

test('an active user reaches the real dashboard normally', function () {
    ['clinic' => $clinic] = setupAccountActiveContext();
    $user = User::factory()->create(['email_verified_at' => now(), 'status' => 'ativo']);
    $clinic->users()->attach($user->id, ['role' => 'professional']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard'));
});

test('a blocked user is shown the account-blocked screen instead of the real page, on any authenticated route', function () {
    ['clinic' => $clinic] = setupAccountActiveContext();
    $user = User::factory()->create(['email_verified_at' => now(), 'status' => 'inativo']);
    $clinic->users()->attach($user->id, ['role' => 'professional']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/AccountBlocked'));

    // Não é um caso especial só do dashboard — qualquer rota autenticada
    // cai na mesma tela, sem nunca chegar no controller de verdade.
    $this->actingAs($user)
        ->get(route('patients.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/AccountBlocked'));
});

test('a blocked user cannot perform write actions — refused with 403, not silently redirected', function () {
    ['clinic' => $clinic] = setupAccountActiveContext();
    $user = User::factory()->create(['email_verified_at' => now(), 'status' => 'inativo']);
    $clinic->users()->attach($user->id, ['role' => 'professional']);

    $this->actingAs($user)
        ->postJson(route('patients.store'), ['nome' => 'Não Deveria', 'sobrenome' => 'Existir'])
        ->assertForbidden();

    $this->assertDatabaseMissing('patients', ['nome' => 'Não Deveria']);
});

test('a blocked user can still log out', function () {
    ['clinic' => $clinic] = setupAccountActiveContext();
    $user = User::factory()->create(['email_verified_at' => now(), 'status' => 'inativo']);
    $clinic->users()->attach($user->id, ['role' => 'professional']);

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

test('a blocked system admin also sees the account-blocked screen in the backoffice', function () {
    $admin = User::factory()->create(['email_verified_at' => now(), 'status' => 'inativo']);
    \App\Models\SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/AccountBlocked'));
});
