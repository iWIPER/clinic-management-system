<?php

use App\Models\Clinic;
use App\Models\Plan;
use App\Models\User;

function setupClinicSuspendedContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-clinicsuspended-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Suspensão', 'slug' => 'clinica-suspensao-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    return compact('plan', 'clinic', 'user');
}

test('a member of an active clinic reaches the real page normally', function () {
    ['user' => $user] = setupClinicSuspendedContext();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard'));
});

test('a member of a suspended clinic is shown the clinic-suspended screen instead of the real page', function () {
    ['clinic' => $clinic, 'user' => $user] = setupClinicSuspendedContext();
    $clinic->update(['status' => 'suspended']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/ClinicSuspended'));

    // Não é caso especial do dashboard — qualquer rota do contexto clínico
    // cai na mesma tela.
    $this->actingAs($user)
        ->get(route('patients.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/ClinicSuspended'));
});

test('a member of a suspended clinic cannot perform write actions — refused with 403', function () {
    ['clinic' => $clinic, 'user' => $user] = setupClinicSuspendedContext();
    $clinic->update(['status' => 'suspended']);

    $this->actingAs($user)
        ->postJson(route('patients.store'), ['nome' => 'Não Deveria', 'sobrenome' => 'Existir'])
        ->assertForbidden();

    $this->assertDatabaseMissing('patients', ['nome' => 'Não Deveria']);
});

test('a member of a suspended clinic can still log out', function () {
    ['clinic' => $clinic, 'user' => $user] = setupClinicSuspendedContext();
    $clinic->update(['status' => 'suspended']);

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});
