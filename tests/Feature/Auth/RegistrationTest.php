<?php

use App\Models\User;

test('a new user can register with a confirmed password and is logged in', function () {
    $response = $this->post(route('register'), [
        'name' => 'Maria Teste',
        'email' => 'maria@example.com',
        'password' => 'senha-super-segura-123',
        'password_confirmation' => 'senha-super-segura-123',
    ]);

    $response->assertRedirect(route('onboarding.choose-role'));
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'maria@example.com']);
});

test('registration fails when the password confirmation does not match', function () {
    $response = $this->post(route('register'), [
        'name' => 'Maria Teste',
        'email' => 'maria2@example.com',
        'password' => 'senha-super-segura-123',
        'password_confirmation' => 'outra-senha-diferente',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'maria2@example.com']);
});

test('registration fails for an email already in use', function () {
    $existing = User::factory()->create();

    $response = $this->post(route('register'), [
        'name' => 'Outro Nome',
        'email' => $existing->email,
        'password' => 'senha-super-segura-123',
        'password_confirmation' => 'senha-super-segura-123',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});
