<?php

use App\Models\Convenio;
use App\Models\Chair;
use App\Models\Clinic;
use App\Models\Plan;
use App\Models\User;

function setupClinicSettingsContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-settings-' . uniqid(),
        'is_free' => true,
        'price_monthly_cents' => 0,
        'price_yearly_cents' => 0,
        'max_clinics' => 1,
        'max_patients' => 100,
        'max_users' => 5,
        'storage_gb' => 1,
        'features' => [],
    ]);

    $clinic = Clinic::create([
        'name' => 'Clínica Configurações',
        'slug' => 'clinica-config-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic');
}

test('the general settings tab still renders', function () {
    ['user' => $user] = setupClinicSettingsContext();

    $this->actingAs($user)
        ->get(route('clinic-settings.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('ClinicSettings/Edit'));
});

test('the chairs settings tab lists the chairs already created for the clinic, same source as the Agenda', function () {
    ['user' => $user, 'clinic' => $clinic] = setupClinicSettingsContext();

    Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira 01', 'color' => '#0d9488']);
    Chair::create(['clinic_id' => $clinic->id, 'name' => 'Cadeira 02', 'color' => '#2563eb']);

    $this->actingAs($user)
        ->get(route('clinic-settings.chairs'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ClinicSettings/Chairs')
            ->has('chairs', 2)
            ->where('chairs.0.name', 'Cadeira 01')
            ->where('chairs.1.name', 'Cadeira 02'));
});

test('a chair created through the settings tab is immediately usable by the Agenda filter', function () {
    ['user' => $user] = setupClinicSettingsContext();

    $this->actingAs($user)
        ->postJson(route('chairs.store'), ['name' => 'Cadeira 03', 'color' => '#7c3aed'])
        ->assertOk();

    $this->actingAs($user)
        ->get(route('clinic-settings.chairs'))
        ->assertInertia(fn ($page) => $page->has('chairs', 1)->where('chairs.0.name', 'Cadeira 03'));

    $this->actingAs($user)
        ->get(route('appointments.index'))
        ->assertInertia(fn ($page) => $page->has('chairs', 1)->where('chairs.0.name', 'Cadeira 03'));
});

test('a clinic cannot see chairs belonging to another clinic in the settings tab', function () {
    ['user' => $user, 'clinic' => $clinic] = setupClinicSettingsContext();
    ['clinic' => $otherClinic] = setupClinicSettingsContext();

    Chair::create(['clinic_id' => $otherClinic->id, 'name' => 'Cadeira Alheia', 'color' => '#0d9488']);

    // A segunda chamada de setupClinicSettingsContext() trocou a clínica
    // ativa da sessão — devolve pra clínica do usuário sendo testado antes
    // de fazer a requisição.
    session(['current_clinic_id' => $clinic->id]);

    $this->actingAs($user)
        ->get(route('clinic-settings.chairs'))
        ->assertInertia(fn ($page) => $page->has('chairs', 0));
});

test('the chairs settings tab exposes the centralized max-chairs limit', function () {
    ['user' => $user] = setupClinicSettingsContext();

    $this->actingAs($user)
        ->get(route('clinic-settings.chairs'))
        ->assertInertia(fn ($page) => $page->where('maxChairs', \App\Models\Chair::MAX_PER_CLINIC));
});

test('navigation between the Convênios and Config. de Documentos settings tabs works', function () {
    ['user' => $user, 'clinic' => $clinic] = setupClinicSettingsContext();

    Convenio::create(['clinic_id' => $clinic->id, 'nome' => 'Particular', 'ativo' => true, 'ordem' => 0]);

    $this->actingAs($user)
        ->get(route('clinic-settings.convenios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('ClinicSettings/Convenios')->has('convenios', 1));

    $this->actingAs($user)
        ->get(route('clinic-settings.documents.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('ClinicSettings/Documents'));
});
