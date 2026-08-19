<?php

use App\Models\Plan;
use App\Models\Clinic;
use App\Models\User;
use App\Models\UserProfileActivityLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function setupProfileContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-profile-' . uniqid(),
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
        'name' => 'Clínica Perfil',
        'slug' => 'clinica-perfil-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
        'storage_disclaimer_confirmed_at' => now(),
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'status' => 'ativo',
    ]);

    $clinic->users()->attach($user->id, ['role' => 'owner']);
    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic');
}

test('profile page renders with extended data', function () {
    ['user' => $user] = setupProfileContext();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Profile/Edit')
            ->has('profile.personal')
            ->has('profile.header')
            ->has('profile.history')
            ->has('profile.permissions')
            ->has('profile.statistics')
            ->has('profile.preferences')
        );
});

test('user can update profile and generates activity log', function () {
    ['user' => $user] = setupProfileContext();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name'      => 'Dr. João Atualizado',
            'email'     => $user->email,
            'phone'     => '(11) 98888-7777',
            'cpf'       => '529.982.247-25',
            'cro'       => '12345',
            'cro_uf'    => 'SP',
            'specialty' => 'Ortodontia',
            'job_title' => 'Dentista',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $user->refresh();

    expect($user->name)->toBe('Dr. João Atualizado')
        ->and($user->phone)->toBe('(11) 98888-7777')
        ->and($user->cro)->toBe('12345')
        ->and($user->profile_updated_at)->not->toBeNull();

    expect(UserProfileActivityLog::where('user_id', $user->id)->count())->toBeGreaterThan(0);
});

test('login updates last_login_at', function () {
    ['user' => $user] = setupProfileContext();

    expect($user->last_login_at)->toBeNull();

    $this->post(route('login'), [
        'email'    => $user->email,
        'password' => 'password',
    ]);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('user can upload and remove profile photo', function () {
    Storage::fake('public');
    ['user' => $user] = setupProfileContext();

    $file = UploadedFile::fake()->image('avatar.jpg', 400, 400);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name'  => $user->name,
            'email' => $user->email,
            'photo' => $file,
        ])
        ->assertRedirect();

    $user->refresh();
    expect($user->profile_photo_path)->not->toBeNull();

    $this->actingAs($user)
        ->delete(route('profile.photo.remove'))
        ->assertRedirect();

    expect($user->fresh()->profile_photo_path)->toBeNull();
});

test('invalid cpf is rejected', function () {
    ['user' => $user] = setupProfileContext();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name'  => $user->name,
            'email' => $user->email,
            'cpf'   => '111.111.111-11',
        ])
        ->assertSessionHasErrors('cpf');
});

test('quick actions can be updated with allowed keys', function () {
    ['user' => $user] = setupProfileContext();

    $this->actingAs($user)
        ->patch(route('profile.quick-actions.update'), [
            'quick_actions' => ['treatments.create', 'inventory.create'],
        ])
        ->assertRedirect();

    expect($user->fresh()->preferences['quick_actions'])
        ->toBe(['treatments.create', 'inventory.create']);
});

test('quick actions rejects a key outside the allowed whitelist', function () {
    ['user' => $user] = setupProfileContext();

    $this->actingAs($user)
        ->patch(route('profile.quick-actions.update'), [
            'quick_actions' => ['clinic-settings.edit'],
        ])
        ->assertSessionHasErrors('quick_actions.0');

    expect($user->fresh()->preferences['quick_actions'] ?? [])->toBe([]);
});

test('quick actions rejects more than 2 selections', function () {
    ['user' => $user] = setupProfileContext();

    $this->actingAs($user)
        ->patch(route('profile.quick-actions.update'), [
            'quick_actions' => ['treatments.create', 'inventory.create', 'document-templates.create'],
        ])
        ->assertSessionHasErrors('quick_actions');
});

test('quick actions can be cleared back to empty', function () {
    ['user' => $user] = setupProfileContext();
    $user->update(['preferences' => ['quick_actions' => ['treatments.create']]]);

    $this->actingAs($user)
        ->patch(route('profile.quick-actions.update'), [
            'quick_actions' => [],
        ])
        ->assertRedirect();

    expect($user->fresh()->preferences['quick_actions'])->toBe([]);
});