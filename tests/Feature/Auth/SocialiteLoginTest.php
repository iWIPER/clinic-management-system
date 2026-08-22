<?php

use App\Models\User;
use Laravel\Socialite\Contracts\Provider as SocialiteProviderContract;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;

function fakeSocialiteUser(string $id, string $email, string $name = 'Usuário Social'): SocialiteUserContract
{
    $user = Mockery::mock(SocialiteUserContract::class);
    $user->shouldReceive('getId')->andReturn($id);
    $user->shouldReceive('getEmail')->andReturn($email);
    $user->shouldReceive('getName')->andReturn($name);

    return $user;
}

test('the login page hides Google/Apple buttons when OAuth is not configured', function () {
    config(['services.google_login.client_id' => null, 'services.apple_login.client_id' => null]);

    $this->get(route('login'))->assertInertia(fn ($page) => $page
        ->where('canUseGoogle', false)
        ->where('canUseApple', false));
});

test('the login page exposes Google/Apple buttons when OAuth is configured', function () {
    config(['services.google_login.client_id' => 'fake-google-id', 'services.apple_login.client_id' => 'fake-apple-id']);

    $this->get(route('login'))->assertInertia(fn ($page) => $page
        ->where('canUseGoogle', true)
        ->where('canUseApple', true));
});

test('the Google OAuth redirect route 404s when Google login is not configured', function () {
    config(['services.google_login.client_id' => null]);

    $this->get(route('oauth.google.redirect'))->assertNotFound();
});

test('the Apple OAuth redirect route 404s when Apple login is not configured', function () {
    config(['services.apple_login.client_id' => null]);

    $this->get(route('oauth.apple.redirect'))->assertNotFound();
});

test('a first-time Google login creates the account and signs the user in', function () {
    config(['services.google_login.client_id' => 'fake-google-id']);

    $provider = Mockery::mock(SocialiteProviderContract::class);
    $provider->shouldReceive('user')->andReturn(fakeSocialiteUser('google-123', 'novo@example.com', 'Novo Usuário'));
    Socialite::shouldReceive('driver')->with('google_login')->andReturn($provider);

    $this->get(route('oauth.google.callback'))->assertRedirect(route('onboarding.choose-role'));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'novo@example.com', 'google_id' => 'google-123']);
});

test('a Google login for an email that already has a password account links it instead of duplicating', function () {
    $existing = User::factory()->create(['email' => 'existente@example.com', 'email_verified_at' => now()]);

    config(['services.google_login.client_id' => 'fake-google-id']);

    $provider = Mockery::mock(SocialiteProviderContract::class);
    $provider->shouldReceive('user')->andReturn(fakeSocialiteUser('google-999', 'existente@example.com'));
    Socialite::shouldReceive('driver')->with('google_login')->andReturn($provider);

    $this->get(route('oauth.google.callback'));

    $this->assertAuthenticatedAs($existing->fresh());
    expect(User::where('email', 'existente@example.com')->count())->toBe(1);
    expect($existing->fresh()->google_id)->toBe('google-999');
});

test('a returning Google user with a linked account logs in without creating a duplicate', function () {
    $existing = User::factory()->create(['email' => 'ja-linkado@example.com', 'google_id' => 'google-555', 'email_verified_at' => now()]);

    config(['services.google_login.client_id' => 'fake-google-id']);

    $provider = Mockery::mock(SocialiteProviderContract::class);
    $provider->shouldReceive('user')->andReturn(fakeSocialiteUser('google-555', 'ja-linkado@example.com'));
    Socialite::shouldReceive('driver')->with('google_login')->andReturn($provider);

    $this->get(route('oauth.google.callback'));

    $this->assertAuthenticatedAs($existing);
    expect(User::where('email', 'ja-linkado@example.com')->count())->toBe(1);
});

test('a failed Google OAuth callback redirects back to login with an error, without authenticating anyone', function () {
    config(['services.google_login.client_id' => 'fake-google-id']);

    $provider = Mockery::mock(SocialiteProviderContract::class);
    $provider->shouldReceive('user')->andThrow(new Exception('invalid_state'));
    Socialite::shouldReceive('driver')->with('google_login')->andReturn($provider);

    $this->get(route('oauth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a first-time Apple sign-in via the form_post callback creates the account and signs the user in', function () {
    config(['services.apple_login.client_id' => 'fake-apple-id']);

    $provider = Mockery::mock(SocialiteProviderContract::class);
    $provider->shouldReceive('user')->andReturn(fakeSocialiteUser('apple-123', 'apple-novo@example.com', 'Usuário Apple'));
    Socialite::shouldReceive('driver')->with('apple_login')->andReturn($provider);

    $this->post(route('oauth.apple.callback'))->assertRedirect(route('onboarding.choose-role'));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'apple-novo@example.com', 'apple_id' => 'apple-123']);
});
