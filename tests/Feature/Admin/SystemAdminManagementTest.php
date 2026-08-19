<?php

use App\Models\AccessLog;
use App\Models\SystemAdmin;
use App\Models\User;
use App\Services\SystemAdminService;
use Illuminate\Validation\ValidationException;

function makeSystemAdmin(): User
{
    $admin = User::factory()->create(['email_verified_at' => now()]);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    return $admin;
}

// ── Bootstrap (comando Artisan) ─────────────────────────────────────────────

test('bootstrap command promotes an existing user by email, with no acting admin, and never creates an account', function () {
    $user = User::factory()->create(['email' => 'suportewildental@gmail.com', 'email_verified_at' => now()]);

    $this->artisan('admin:grant-system-admin', ['email' => 'suportewildental@gmail.com'])
        ->assertSuccessful();

    expect($user->fresh()->isSystemAdmin())->toBeTrue();
    $grant = SystemAdmin::active()->where('user_id', $user->id)->first();
    expect($grant->granted_by_id)->toBeNull();
});

test('bootstrap command refuses to create a fictitious account when the email does not exist yet', function () {
    expect(User::where('email', 'nao-existe-ainda@example.com')->exists())->toBeFalse();

    $this->artisan('admin:grant-system-admin', ['email' => 'nao-existe-ainda@example.com'])
        ->assertFailed();

    expect(User::where('email', 'nao-existe-ainda@example.com')->exists())->toBeFalse();
});

// ── Conceder / remover via serviço (usado pelo controller HTTP) ────────────

test('granting system admin creates an active grant and an audit log entry', function () {
    $granter = makeSystemAdmin();
    $target  = User::factory()->create(['email_verified_at' => now()]);

    app(SystemAdminService::class)->grant($target, $granter);

    expect($target->fresh()->isSystemAdmin())->toBeTrue()
        ->and(AccessLog::where('action', 'system_admin_granted')->where('user_id', $granter->id)->exists())->toBeTrue();
});

test('granting system admin to someone who already has it is rejected', function () {
    $granter = makeSystemAdmin();
    $target  = makeSystemAdmin();

    expect(fn () => app(SystemAdminService::class)->grant($target, $granter))
        ->toThrow(ValidationException::class);
});

test('revoking system admin removes access and logs who revoked it, preserving the historical record', function () {
    $revoker = makeSystemAdmin();
    $target  = makeSystemAdmin();

    app(SystemAdminService::class)->revoke($target, $revoker);

    expect($target->fresh()->isSystemAdmin())->toBeFalse();

    $grant = SystemAdmin::where('user_id', $target->id)->first();
    expect($grant)->not->toBeNull() // não apaga a linha, só marca revogado
        ->and($grant->revoked_at)->not->toBeNull()
        ->and($grant->revoked_by_id)->toBe($revoker->id)
        ->and(AccessLog::where('action', 'system_admin_revoked')->exists())->toBeTrue();
});

test('the last system admin cannot be removed by someone else', function () {
    $lastAdmin = makeSystemAdmin();
    $otherAdminActing = User::factory()->create(['email_verified_at' => now()]); // não é admin, só aciona pra teste

    expect(fn () => app(SystemAdminService::class)->revoke($lastAdmin, $otherAdminActing))
        ->toThrow(ValidationException::class, 'Não é possível remover o último System Admin da plataforma.');

    expect($lastAdmin->fresh()->isSystemAdmin())->toBeTrue();
});

test('self-removal is blocked when it would leave the platform without any system admin', function () {
    $lastAdmin = makeSystemAdmin();

    expect(fn () => app(SystemAdminService::class)->revoke($lastAdmin, $lastAdmin))
        ->toThrow(ValidationException::class);

    expect($lastAdmin->fresh()->isSystemAdmin())->toBeTrue();
});

test('self-removal is allowed when another system admin still remains', function () {
    $admin1 = makeSystemAdmin();
    $admin2 = makeSystemAdmin();

    app(SystemAdminService::class)->revoke($admin1, $admin1);

    expect($admin1->fresh()->isSystemAdmin())->toBeFalse()
        ->and($admin2->fresh()->isSystemAdmin())->toBeTrue();
});

test('platform supports more than one system admin simultaneously', function () {
    $admin1 = makeSystemAdmin();
    $admin2 = makeSystemAdmin();
    $admin3 = makeSystemAdmin();

    expect(SystemAdmin::active()->count())->toBe(3)
        ->and($admin1->fresh()->isSystemAdmin())->toBeTrue()
        ->and($admin2->fresh()->isSystemAdmin())->toBeTrue()
        ->and($admin3->fresh()->isSystemAdmin())->toBeTrue();
});

// ── Endpoints HTTP ───────────────────────────────────────────────────────

test('HTTP: a system admin can promote another existing user via the store endpoint', function () {
    $admin  = makeSystemAdmin();
    $target = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($admin)
        ->postJson(route('admin.system-admins.store'), ['email' => $target->email])
        ->assertOk();

    expect($target->fresh()->isSystemAdmin())->toBeTrue();
});

test('HTTP: promoting a nonexistent email is rejected, never inventing a user', function () {
    $admin = makeSystemAdmin();

    $this->actingAs($admin)
        ->postJson(route('admin.system-admins.store'), ['email' => 'ninguem@example.com'])
        ->assertStatus(422);

    expect(User::where('email', 'ninguem@example.com')->exists())->toBeFalse();
});

test('HTTP: cannot promote via request manipulation without a valid session as system admin', function () {
    $normalUser = User::factory()->create(['email_verified_at' => now()]);
    $target     = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($normalUser)
        ->postJson(route('admin.system-admins.store'), ['email' => $target->email])
        ->assertForbidden();

    expect($target->fresh()->isSystemAdmin())->toBeFalse();
});

test('HTTP: removing the last system admin via the endpoint is rejected with a clear error', function () {
    $lastAdmin = makeSystemAdmin();

    $this->actingAs($lastAdmin)
        ->deleteJson(route('admin.system-admins.destroy', $lastAdmin->id))
        ->assertStatus(422);

    expect($lastAdmin->fresh()->isSystemAdmin())->toBeTrue();
});

test('HTTP: index lists active admins with who granted them, and never a revoked one', function () {
    $admin1 = makeSystemAdmin();
    $admin2 = makeSystemAdmin();
    app(SystemAdminService::class)->revoke($admin2, $admin1);

    $response = $this->actingAs($admin1)->get(route('admin.system-admins'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/SystemAdmins/Index')
        ->has('admins', 1)
        ->where('admins.0.user.id', $admin1->id)
    );
});
