<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature');

function setupDriveUploadContext(): array
{
    $plan = \App\Models\Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-drive-' . uniqid(),
        'is_free' => true,
        'price_monthly_cents' => 0,
        'price_yearly_cents' => 0,
        'max_clinics' => 1,
        'max_patients' => 100,
        'max_users' => 5,
        'storage_gb' => 1,
        'features' => [],
    ]);

    $clinic = \App\Models\Clinic::create([
        'name' => 'Clínica Drive',
        'slug' => 'clinica-drive-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
        'storage_disclaimer_confirmed_at' => now(),
    ]);

    $user = \App\Models\User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    $patient = \App\Models\Patient::create([
        'clinic_id' => $clinic->id,
        'nome' => 'João',
        'sobrenome' => 'Silva',
        'status' => 'ativo',
    ]);

    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic', 'patient');
}