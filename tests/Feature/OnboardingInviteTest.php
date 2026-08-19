<?php

use App\Mail\TeamInviteMail;
use App\Models\Clinic;
use App\Models\Invite;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Fase A, item 6 — o wizard de onboarding criava um Invite mas nunca
 * enviava e-mail (TODO esquecido), embora mostrasse "Convites enviados com
 * sucesso!". Agora reusa InviteService::createOrUpdate()/dispatchEmail(),
 * o mesmo usado pela tela Equipe — nenhum serviço novo foi criado.
 */
function setupOnboardingInviteContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-invite-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);

    $clinic = Clinic::create([
        'name' => 'Clínica Onboarding Convites', 'slug' => 'clinica-onb-invite-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista Administrador', 'status' => 'ativo']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    session(['current_clinic_id' => $clinic->id]);

    return compact('plan', 'clinic', 'user');
}

test('sending an onboarding invite creates the invite and actually sends TeamInviteMail', function () {
    Mail::fake();
    ['user' => $user, 'clinic' => $clinic] = setupOnboardingInviteContext();

    $this->actingAs($user)
        ->post(route('onboarding.invite-team'), [
            'invites' => [
                ['email' => 'convidado@example.com', 'role' => 'professional'],
            ],
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Convite enviado com sucesso!');

    $invite = Invite::where('clinic_id', $clinic->id)->where('email', 'convidado@example.com')->first();

    expect($invite)->not->toBeNull()
        ->and($invite->job_title)->toBe('Dentista')
        ->and($invite->role)->toBe('professional')
        ->and($invite->short_token)->not->toBeNull()
        ->and($invite->status)->toBe('pending');

    Mail::assertSent(TeamInviteMail::class, fn ($mail) => $mail->invite->id === $invite->id);
});

test('onboarding invite maps each role to the equivalent job_title used by InviteService', function () {
    Mail::fake();
    ['user' => $user, 'clinic' => $clinic] = setupOnboardingInviteContext();

    $this->actingAs($user)->post(route('onboarding.invite-team'), [
        'invites' => [
            ['email' => 'admin@example.com', 'role' => 'admin'],
            ['email' => 'staff@example.com', 'role' => 'staff'],
        ],
    ])->assertRedirect(route('dashboard'));

    $admin = Invite::where('clinic_id', $clinic->id)->where('email', 'admin@example.com')->firstOrFail();
    $staff = Invite::where('clinic_id', $clinic->id)->where('email', 'staff@example.com')->firstOrFail();

    expect($admin->job_title)->toBe('Administrador')
        ->and($staff->job_title)->toBe('Secretário(a)');

    Mail::assertSent(TeamInviteMail::class, 2);
});

test('sending multiple onboarding invites reports the real count sent, not a generic message', function () {
    Mail::fake();
    ['user' => $user] = setupOnboardingInviteContext();

    $this->actingAs($user)
        ->post(route('onboarding.invite-team'), [
            'invites' => [
                ['email' => 'um@example.com', 'role' => 'professional'],
                ['email' => 'dois@example.com', 'role' => 'staff'],
            ],
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', '2 convites enviados com sucesso!');
});
