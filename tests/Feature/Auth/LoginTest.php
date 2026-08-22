<?php

use App\Models\Clinic;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

function setupLoginContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-login-'.uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Login', 'slug' => 'clinica-login-'.uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);

    return compact('plan', 'clinic');
}

test('a user with valid credentials is authenticated and redirected to the dashboard', function () {
    ['clinic' => $clinic] = setupLoginContext();
    $user = User::factory()->create(['email_verified_at' => now(), 'password' => bcrypt('senha-correta')]);
    $clinic->users()->attach($user->id, ['role' => 'professional']);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'senha-correta',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('login fails with an invalid password without authenticating', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'password' => bcrypt('senha-correta')]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'senha-errada',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login fails for an email that does not exist, without revealing that', function () {
    $this->post(route('login'), [
        'email' => 'ninguem@example.com',
        'password' => 'qualquer-coisa',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a blocked user can still log in — the block is enforced after authentication, not at login', function () {
    ['clinic' => $clinic] = setupLoginContext();
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'password' => bcrypt('senha-correta'),
        'status' => 'inativo',
    ]);
    $clinic->users()->attach($user->id, ['role' => 'professional']);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'senha-correta',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('repeated failed login attempts for the same email+ip are rate limited', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'password' => bcrypt('senha-correta')]);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);
    }

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'senha-correta',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->assertStringContainsString('Muitas tentativas', collect($response->getSession()->get('errors')->get('email'))->first() ?? '');
});

test('the rate limit is scoped to email+ip and cleared after a successful login', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'password' => bcrypt('senha-correta')]);

    $key = Str::transliterate(Str::lower($user->email)).'|127.0.0.1';

    RateLimiter::hit($key);
    RateLimiter::hit($key);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'senha-correta',
    ])->assertRedirect(route('onboarding.choose-role'));

    expect(RateLimiter::attempts($key))->toBe(0);
});

test('an authenticated protected route redirects a guest to login instead of exposing data', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('a forged, non-existent session cookie does not grant access to a protected route', function () {
    $this->withUnencryptedCookie(config('session.cookie'), 'this-session-id-was-never-issued-by-the-server')
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('a logged out user session cannot be reused to access protected routes', function () {
    ['clinic' => $clinic] = setupLoginContext();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'professional']);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $this->post(route('logout'))->assertRedirect('/');

    $this->assertGuest();
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
