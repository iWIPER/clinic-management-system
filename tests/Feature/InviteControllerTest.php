<?php

use App\Models\Clinic;
use App\Models\Invite;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function setupInviteHttpContext(string $suffix = '', string $role = 'owner'): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-invite' . $suffix . '-' . uniqid(),
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
        'name' => 'Clínica Convites' . $suffix,
        'slug' => 'clinica-convites' . $suffix . '-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => $role]);

    return compact('clinic', 'user');
}

beforeEach(function () {
    Mail::fake();
});

// ── check / store — só owner/admin ──────────────────────────────────────
describe('check and store — authorization', function () {
    test('owner can check a scenario and create an invite', function () {
        ['clinic' => $clinic, 'user' => $user] = setupInviteHttpContext();

        $this->actingAs($user)->withSession(['current_clinic_id' => $clinic->id])
            ->postJson(route('invites.check'), ['email' => 'novo@example.com'])
            ->assertOk()
            ->assertJsonPath('scenario', 'NEW');

        $this->actingAs($user)->withSession(['current_clinic_id' => $clinic->id])
            ->postJson(route('invites.store'), [
                'name' => 'Fulano',
                'email' => 'novo@example.com',
                'job_title' => 'Dentista',
            ])
            ->assertOk()
            ->assertJsonStructure(['invite' => ['id'], 'invite_link']);

        expect(Invite::where('email', 'novo@example.com')->where('clinic_id', $clinic->id)->exists())->toBeTrue();
        Mail::assertSent(\App\Mail\TeamInviteMail::class);
    });

    test('a staff member (non owner/admin) cannot check or create invites', function () {
        ['clinic' => $clinic, 'user' => $user] = setupInviteHttpContext('-staff', 'staff');

        $this->actingAs($user)->withSession(['current_clinic_id' => $clinic->id])
            ->postJson(route('invites.check'), ['email' => 'novo@example.com'])
            ->assertStatus(403);

        $this->actingAs($user)->withSession(['current_clinic_id' => $clinic->id])
            ->postJson(route('invites.store'), [
                'name' => 'Fulano', 'email' => 'novo@example.com', 'job_title' => 'Dentista',
            ])
            ->assertStatus(403);

        expect(Invite::where('email', 'novo@example.com')->exists())->toBeFalse();
    });

    test('store refuses to create an invite for someone who is already a clinic member', function () {
        ['clinic' => $clinic, 'user' => $user] = setupInviteHttpContext();
        $existingMember = User::factory()->create(['email' => 'membro@example.com']);
        $clinic->users()->attach($existingMember->id, ['role' => 'staff']);

        $this->actingAs($user)->withSession(['current_clinic_id' => $clinic->id])
            ->postJson(route('invites.store'), [
                'name' => 'Membro', 'email' => 'membro@example.com', 'job_title' => 'Dentista',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'already_member');
    });

    test('unauthenticated requests are redirected to login', function () {
        $this->postJson(route('invites.check'), ['email' => 'x@example.com'])->assertStatus(401);
    });
});

// ── destroy / resend / regenerateToken / reactivate — tenant isolation ──
describe('mutation endpoints — tenant isolation', function () {
    test('owner can cancel, resend, regenerate and reactivate an invite from their own clinic', function () {
        ['clinic' => $clinic, 'user' => $user] = setupInviteHttpContext();
        $invite = Invite::create([
            'clinic_id' => $clinic->id, 'type' => 'team', 'email' => 'x@example.com',
            'name' => 'X', 'job_title' => 'Dentista', 'invited_by_id' => $user->id,
        ]);

        $this->actingAs($user)->withSession(['current_clinic_id' => $clinic->id])
            ->postJson(route('invites.resend', $invite))
            ->assertOk();

        $this->actingAs($user)->withSession(['current_clinic_id' => $clinic->id])
            ->postJson(route('invites.regenerate', $invite))
            ->assertOk();

        $this->actingAs($user)->withSession(['current_clinic_id' => $clinic->id])
            ->postJson(route('invites.reactivate', $invite))
            ->assertOk();

        $this->actingAs($user)->withSession(['current_clinic_id' => $clinic->id])
            ->deleteJson(route('invites.destroy', $invite))
            ->assertOk()
            ->assertJsonPath('ok', true);

        expect($invite->fresh()->status)->toBe('cancelled');
    });

    test('a user from another clinic cannot resend, regenerate, reactivate or cancel a foreign invite', function () {
        ['clinic' => $clinicA, 'user' => $userA] = setupInviteHttpContext('-a');
        $invite = Invite::create([
            'clinic_id' => $clinicA->id, 'type' => 'team', 'email' => 'x@example.com',
            'name' => 'X', 'job_title' => 'Dentista', 'invited_by_id' => $userA->id,
        ]);

        ['clinic' => $clinicB, 'user' => $userB] = setupInviteHttpContext('-b');

        $this->actingAs($userB)->withSession(['current_clinic_id' => $clinicB->id])
            ->postJson(route('invites.resend', $invite))->assertStatus(403);

        $this->actingAs($userB)->withSession(['current_clinic_id' => $clinicB->id])
            ->postJson(route('invites.regenerate', $invite))->assertStatus(403);

        $this->actingAs($userB)->withSession(['current_clinic_id' => $clinicB->id])
            ->postJson(route('invites.reactivate', $invite))->assertStatus(403);

        $this->actingAs($userB)->withSession(['current_clinic_id' => $clinicB->id])
            ->deleteJson(route('invites.destroy', $invite))->assertStatus(403);

        expect($invite->fresh()->status)->toBe('pending');
    });

    test('resend refuses an already expired invite', function () {
        ['clinic' => $clinic, 'user' => $user] = setupInviteHttpContext();
        $invite = Invite::create([
            'clinic_id' => $clinic->id, 'type' => 'team', 'email' => 'x@example.com',
            'name' => 'X', 'job_title' => 'Dentista', 'invited_by_id' => $user->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->withSession(['current_clinic_id' => $clinic->id])
            ->postJson(route('invites.resend', $invite))
            ->assertStatus(422);
    });
});

// ── show / accept — página pública ────────────────────────────────────────
// Auditoria de segurança — short_token (formato AAA-999, ~27 bits de
// entropia) era aceito sozinho como credencial de aceite, sem rate limit.
// Agora só o token forte (Str::random(32)) é aceito por show()/accept(); o
// short_token continua existindo só como código de referência visual.
describe('show and accept — public acceptance flow', function () {
    test('show renders the accept page for a valid pending invite', function () {
        ['clinic' => $clinic, 'user' => $user] = setupInviteHttpContext();
        $invite = Invite::create([
            'clinic_id' => $clinic->id, 'type' => 'team', 'email' => 'convidado@example.com',
            'name' => 'Convidado', 'job_title' => 'Dentista', 'invited_by_id' => $user->id,
        ]);

        $this->get(route('invites.show', $invite->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Invites/Accept')
                ->where('invite.email', 'convidado@example.com')
                ->where('userExists', false)
            );
    });

    test('show renders an invalid-invite page for an expired invite, without exposing accept', function () {
        ['clinic' => $clinic, 'user' => $user] = setupInviteHttpContext();
        $invite = Invite::create([
            'clinic_id' => $clinic->id, 'type' => 'team', 'email' => 'x@example.com',
            'name' => 'X', 'job_title' => 'Dentista', 'invited_by_id' => $user->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('invites.show', $invite->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Invites/Invalid')
                ->where('reason', 'expired')
            );
    });

    test('show 404s for a token that does not exist', function () {
        $this->get(route('invites.show', 'NOPE0000NOPE0000NOPE0000NOPE0000'))->assertStatus(404);
    });

    // D) short_token isoladamente não consegue acessar/aceitar convite
    test('the short_token alone cannot be used to view or accept an invite', function () {
        ['clinic' => $clinic, 'user' => $user] = setupInviteHttpContext();
        $invite = Invite::create([
            'clinic_id' => $clinic->id, 'type' => 'team', 'email' => 'convidado@example.com',
            'name' => 'Convidado', 'job_title' => 'Dentista', 'invited_by_id' => $user->id,
        ]);

        $this->get(route('invites.show', $invite->short_token))->assertStatus(404);

        $this->post(route('invites.accept', $invite->short_token), [
            'password' => 'senha12345',
            'password_confirmation' => 'senha12345',
        ])->assertStatus(404);

        expect(User::where('email', 'convidado@example.com')->exists())->toBeFalse();
    });

    // A) convite válido → aceitação funciona (via token forte)
    test('accept creates a new user, attaches them to the correct clinic with the invited job_title, and logs them in', function () {
        ['clinic' => $clinic, 'user' => $inviter] = setupInviteHttpContext();
        $invite = Invite::create([
            'clinic_id' => $clinic->id, 'type' => 'team', 'email' => 'novomembro@example.com',
            'name' => 'Novo Membro', 'job_title' => 'Secretário(a)', 'invited_by_id' => $inviter->id,
        ]);

        $this->post(route('invites.accept', $invite->token), [
            'password' => 'senha12345',
            'password_confirmation' => 'senha12345',
        ])->assertRedirect(route('dashboard'));

        $newUser = User::where('email', 'novomembro@example.com')->firstOrFail();
        expect($newUser->job_title)->toBe('Secretário(a)')
            ->and($clinic->users()->where('users.id', $newUser->id)->exists())->toBeTrue()
            ->and($invite->fresh()->status)->toBe('accepted');

        $this->assertAuthenticatedAs($newUser);
    });

    // B) convite expirado → rejeitado
    test('accept rejects an expired invite with 410, without creating a user', function () {
        ['clinic' => $clinic, 'user' => $inviter] = setupInviteHttpContext();
        $invite = Invite::create([
            'clinic_id' => $clinic->id, 'type' => 'team', 'email' => 'tarde@example.com',
            'name' => 'Tarde', 'job_title' => 'Dentista', 'invited_by_id' => $inviter->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->post(route('invites.accept', $invite->token), [
            'password' => 'senha12345',
            'password_confirmation' => 'senha12345',
        ])->assertStatus(410);

        expect(User::where('email', 'tarde@example.com')->exists())->toBeFalse();
    });

    // C) token inválido → rejeitado
    test('accept rejects an invalid/unknown token with 404', function () {
        $this->post(route('invites.accept', 'NOPE0000NOPE0000NOPE0000NOPE0000'), [
            'password' => 'senha12345',
            'password_confirmation' => 'senha12345',
        ])->assertStatus(404);
    });

    // H) usuário existente aceita mediante senha correta; G) papel de admin
    // só é concedido quando o convite legítimo (token forte) é usado
    test('accept for an existing user requires the correct password and rejects a wrong one', function () {
        ['clinic' => $clinic, 'user' => $inviter] = setupInviteHttpContext();
        $existingUser = User::factory()->create([
            'email' => 'existente@example.com',
            'password' => bcrypt('senha-correta'),
        ]);
        $invite = Invite::create([
            'clinic_id' => $clinic->id, 'type' => 'team', 'email' => 'existente@example.com',
            'name' => 'Existente', 'job_title' => 'Administrador', 'role' => 'admin', 'invited_by_id' => $inviter->id,
        ]);

        $this->post(route('invites.accept', $invite->token), [
            'password' => 'senha-errada',
        ])->assertSessionHasErrors('password');

        expect($clinic->users()->where('users.id', $existingUser->id)->exists())->toBeFalse();

        $this->post(route('invites.accept', $invite->token), [
            'password' => 'senha-correta',
        ])->assertRedirect(route('dashboard'));

        expect($clinic->users()->where('users.id', $existingUser->id)->wherePivot('role', 'admin')->exists())->toBeTrue();
    });

    // E) excesso de tentativas de senha → rate limit
    test('too many wrong-password attempts against an existing user are rate limited', function () {
        ['clinic' => $clinic, 'user' => $inviter] = setupInviteHttpContext();
        User::factory()->create([
            'email' => 'alvo@example.com',
            'password' => bcrypt('senha-correta'),
        ]);
        $invite = Invite::create([
            'clinic_id' => $clinic->id, 'type' => 'team', 'email' => 'alvo@example.com',
            'name' => 'Alvo', 'job_title' => 'Dentista', 'invited_by_id' => $inviter->id,
        ]);

        foreach (range(1, 5) as $_) {
            $this->post(route('invites.accept', $invite->token), ['password' => 'senha-errada'])
                ->assertSessionHasErrors('password');
        }

        // 6ª tentativa, mesmo com a senha CORRETA, é bloqueada pelo rate limit.
        $this->post(route('invites.accept', $invite->token), ['password' => 'senha-correta'])
            ->assertSessionHasErrors('password');

        expect($clinic->users()->where('users.email', 'alvo@example.com')->exists())->toBeFalse();
    });

    // F) convite de Clínica A não pode ser usado pra ingressar/manipular Clínica B —
    // multi-tenancy: um usuário já pertencente à clínica A que aceita um
    // convite da clínica B passa a pertencer às duas, sem remoção do
    // vínculo original nem exposição de dados da outra clínica.
    test('accepting an invite never exposes or grants access to a different clinic than the one on the invite', function () {
        ['clinic' => $clinicA, 'user' => $inviterA] = setupInviteHttpContext('-a');
        ['clinic' => $clinicB] = setupInviteHttpContext('-b');

        $invite = Invite::create([
            'clinic_id' => $clinicA->id, 'type' => 'team', 'email' => 'novo@example.com',
            'name' => 'Novo', 'job_title' => 'Dentista', 'invited_by_id' => $inviterA->id,
        ]);

        $this->post(route('invites.accept', $invite->token), [
            'password' => 'senha12345',
            'password_confirmation' => 'senha12345',
        ])->assertRedirect(route('dashboard'));

        $newUser = User::where('email', 'novo@example.com')->firstOrFail();
        expect($clinicA->users()->where('users.id', $newUser->id)->exists())->toBeTrue()
            ->and($clinicB->users()->where('users.id', $newUser->id)->exists())->toBeFalse();
    });
});

// ── Regra definitiva: convite de equipe nunca vale mais de 7 dias ──────────
// A regra é do backend (Invite::boot(), evento saving), não só do frontend —
// nenhum chamador consegue conceder mais que Invite::MAX_VALIDITY_DAYS.
describe('7-day maximum validity — backend authority', function () {
    // A) convite recém-criado → expira em no máximo 7 dias
    test('a newly created invite expires in at most 7 days', function () {
        ['clinic' => $clinic, 'user' => $user] = setupInviteHttpContext();
        $invite = Invite::create([
            'clinic_id' => $clinic->id, 'type' => 'team', 'email' => 'novo@example.com',
            'name' => 'Novo', 'job_title' => 'Dentista', 'invited_by_id' => $user->id,
        ]);

        expect($invite->expires_at->isFuture())->toBeTrue()
            ->and($invite->expires_at->lte(now()->addDays(Invite::MAX_VALIDITY_DAYS)->addMinute()))->toBeTrue();
    });

    // B) convite expirado → não pode ser aceito (nem visualizado)
    test('an expired invite cannot be viewed or accepted', function () {
        ['clinic' => $clinic, 'user' => $user] = setupInviteHttpContext();
        $invite = Invite::create([
            'clinic_id' => $clinic->id, 'type' => 'team', 'email' => 'tarde@example.com',
            'name' => 'Tarde', 'job_title' => 'Dentista', 'invited_by_id' => $user->id,
        ]);
        $invite->forceFill(['expires_at' => now()->subDay()])->saveQuietly();

        $this->get(route('invites.show', $invite->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Invites/Invalid')->where('reason', 'expired'));

        $this->post(route('invites.accept', $invite->token), [
            'password' => 'senha12345', 'password_confirmation' => 'senha12345',
        ])->assertStatus(410);

        expect(User::where('email', 'tarde@example.com')->exists())->toBeFalse();
    });

    // C) expires_at manipulado além de 7 dias → o backend nunca permite
    test('the model never persists an expires_at beyond MAX_VALIDITY_DAYS, no matter what a caller passes', function () {
        ['clinic' => $clinic, 'user' => $user] = setupInviteHttpContext();

        $invite = Invite::create([
            'clinic_id' => $clinic->id, 'type' => 'team', 'email' => 'longo@example.com',
            'name' => 'Longo', 'job_title' => 'Dentista', 'invited_by_id' => $user->id,
            'expires_at' => now()->addDays(60),
        ]);

        expect($invite->expires_at->lte(now()->addDays(Invite::MAX_VALIDITY_DAYS)->addMinute()))->toBeTrue();

        // Mesma tentativa via update() — a autoridade é o evento saving, não
        // um caminho específico de criação.
        $invite->update(['expires_at' => now()->addDays(90)]);
        expect($invite->fresh()->expires_at->lte(now()->addDays(Invite::MAX_VALIDITY_DAYS)->addMinute()))->toBeTrue();
    });

    test('a legacy row with expires_at beyond 7 days from creation is still treated as expired', function () {
        ['clinic' => $clinic, 'user' => $user] = setupInviteHttpContext();
        $invite = Invite::create([
            'clinic_id' => $clinic->id, 'type' => 'team', 'email' => 'legado@example.com',
            'name' => 'Legado', 'job_title' => 'Dentista', 'invited_by_id' => $user->id,
        ]);

        // Simula um registro legado (anterior à regra), sem passar pelo
        // evento saving — expires_at bem no futuro, mas created_at antigo.
        $invite->forceFill(['expires_at' => now()->addDays(60), 'created_at' => now()->subDays(8)])->saveQuietly();

        expect($invite->fresh()->isExpired())->toBeTrue();
    });

    // D) reenvio → nova credencial segura + nova validade máxima de 7 dias
    test('resending an invite rotates the token and refreshes the expiry, not just the expiry', function () {
        ['clinic' => $clinic, 'user' => $user] = setupInviteHttpContext();
        $invite = Invite::create([
            'clinic_id' => $clinic->id, 'type' => 'team', 'email' => 'reenvio@example.com',
            'name' => 'Reenvio', 'job_title' => 'Dentista', 'invited_by_id' => $user->id,
        ]);
        $oldToken = $invite->token;
        $oldShortToken = $invite->short_token;

        $this->actingAs($user)->withSession(['current_clinic_id' => $clinic->id])
            ->postJson(route('invites.resend', $invite))
            ->assertOk();

        $fresh = $invite->fresh();
        expect($fresh->token)->not->toBe($oldToken)
            ->and($fresh->short_token)->not->toBe($oldShortToken)
            ->and($fresh->expires_at->lte(now()->addDays(Invite::MAX_VALIDITY_DAYS)->addMinute()))->toBeTrue();

        // O link antigo (token rotacionado) deixa de funcionar.
        $this->post(route('invites.accept', $oldToken), [
            'password' => 'senha12345', 'password_confirmation' => 'senha12345',
        ])->assertStatus(404);

        // O novo token funciona normalmente.
        $this->post(route('invites.accept', $fresh->token), [
            'password' => 'senha12345', 'password_confirmation' => 'senha12345',
        ])->assertRedirect(route('dashboard'));
    });

    // E) revogação (cancelamento) → convite não pode mais ser utilizado
    test('a cancelled invite cannot be accepted even before its expiry date', function () {
        ['clinic' => $clinic, 'user' => $user] = setupInviteHttpContext();
        $invite = Invite::create([
            'clinic_id' => $clinic->id, 'type' => 'team', 'email' => 'revogado@example.com',
            'name' => 'Revogado', 'job_title' => 'Dentista', 'invited_by_id' => $user->id,
        ]);

        $this->actingAs($user)->withSession(['current_clinic_id' => $clinic->id])
            ->deleteJson(route('invites.destroy', $invite))
            ->assertOk();

        expect($invite->fresh()->status)->toBe('cancelled');

        $this->post(route('invites.accept', $invite->token), [
            'password' => 'senha12345', 'password_confirmation' => 'senha12345',
        ])->assertStatus(410);

        expect(User::where('email', 'revogado@example.com')->exists())->toBeFalse();
    });
});
