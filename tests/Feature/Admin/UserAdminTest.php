<?php

use App\Models\AccessLog;
use App\Models\Clinic;
use App\Models\ClinicalRecord;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\SystemAdmin;
use App\Models\User;
use App\Services\UserRemovalService;
use Illuminate\Validation\ValidationException;

function setupUserAdminContext(string $suffix = ''): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-useradmin' . $suffix . '-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica User Admin' . $suffix, 'slug' => 'clinica-useradmin' . $suffix . '-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);

    $sysAdmin = User::factory()->create(['email_verified_at' => now()]);
    SystemAdmin::create(['user_id' => $sysAdmin->id, 'granted_at' => now()]);

    return compact('plan', 'clinic', 'sysAdmin');
}

// ── Listagem / detalhe ───────────────────────────────────────────────────

test('index lists users globally with search and status filter', function () {
    ['sysAdmin' => $admin] = setupUserAdminContext();
    $target = User::factory()->create(['name' => 'Fulano Buscável', 'email_verified_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.users', ['search' => 'Buscável']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Users/Index')->has('users.data', 1)->where('users.data.0.id', $target->id));
});

test('show renders user detail with clinics and role per clinic', function () {
    ['clinic' => $clinic, 'sysAdmin' => $admin] = setupUserAdminContext();
    $target = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($target->id, ['role' => 'professional']);

    $this->actingAs($admin)
        ->get(route('admin.users.show', $target->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Show')
            ->where('targetUser.id', $target->id)
            ->where('clinics.0.role', 'professional')
        );
});

// ── Bloqueio real (achado crítico: status não era enforced antes) ─────────

test('blocking a user actually prevents login, not just a cosmetic flag', function () {
    ['sysAdmin' => $admin] = setupUserAdminContext();
    $target = User::factory()->create(['email_verified_at' => now(), 'password' => bcrypt('senha-correta-123')]);

    $this->actingAs($admin)->postJson(route('admin.users.block', $target->id))->assertOk();
    expect($target->fresh()->status)->toBe('inativo');

    // logout explícito: a rota /login tem middleware 'guest' — continuar
    // autenticado como $admin faria o próprio guest middleware redirecionar
    // antes de LoginRequest::authenticate() rodar, mascarando o teste.
    $this->post(route('logout'));

    $this->post(route('login'), ['email' => $target->email, 'password' => 'senha-correta-123'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('unblocking a user restores real login', function () {
    ['sysAdmin' => $admin] = setupUserAdminContext();
    $target = User::factory()->create(['email_verified_at' => now(), 'password' => bcrypt('senha-correta-123')]);

    $this->actingAs($admin)->postJson(route('admin.users.block', $target->id))->assertOk();
    $this->actingAs($admin)->postJson(route('admin.users.unblock', $target->id))->assertOk();

    $this->post(route('logout'));

    $this->post(route('login'), ['email' => $target->email, 'password' => 'senha-correta-123'])
        ->assertRedirect();
    $this->assertAuthenticatedAs($target->fresh());
});

test('a system admin cannot block their own account', function () {
    ['sysAdmin' => $admin] = setupUserAdminContext();

    $this->actingAs($admin)->postJson(route('admin.users.block', $admin->id))->assertForbidden();
    expect($admin->fresh()->status)->not->toBe('inativo');
});

// ── Exclusão — integridade (o achado central desta fase) ──────────────────

test('deleting a user with no clinical history and not a clinic owner performs a real physical delete', function () {
    ['sysAdmin' => $admin] = setupUserAdminContext();
    $target = User::factory()->create(['email_verified_at' => now()]);
    $targetId = $target->id;

    $result = app(UserRemovalService::class)->remove($target, $admin);

    expect($result)->toBe('deleted')
        ->and(User::find($targetId))->toBeNull()
        ->and(AccessLog::where('action', 'admin_user_deleted')->exists())->toBeTrue();
});

test('deleting a user with real clinical history anonymizes instead of deleting — clinical record survives intact', function () {
    ['clinic' => $clinic, 'sysAdmin' => $admin] = setupUserAdminContext();
    $target = User::factory()->create(['email_verified_at' => now(), 'name' => 'Dra. Com Histórico', 'email' => 'com-historico@example.com']);
    $clinic->users()->attach($target->id, ['role' => 'professional']);

    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Paciente', 'sobrenome' => 'Teste', 'status' => 'ativo']);
    $record = ClinicalRecord::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $target->id,
        'procedure_name' => 'Limpeza',
    ]);

    $result = app(UserRemovalService::class)->remove($target, $admin);

    expect($result)->toBe('anonymized');

    $target->refresh();
    expect($target->name)->toBe('Usuário removido')
        ->and($target->email)->not->toBe('com-historico@example.com')
        ->and($target->status)->toBe('inativo')
        ->and($target->clinics()->count())->toBe(0);

    // O registro clínico em si nunca é tocado — mesma linha, mesmo professional_id.
    $record->refresh();
    expect($record->professional_id)->toBe($target->id)
        ->and(ClinicalRecord::count())->toBe(1);

    expect(AccessLog::where('action', 'admin_user_anonymized')->exists())->toBeTrue();
});

test('deleting the last system admin is refused, preserving platform administration', function () {
    ['sysAdmin' => $admin] = setupUserAdminContext();

    expect(fn () => app(UserRemovalService::class)->remove($admin, $admin))
        ->toThrow(ValidationException::class, 'Não é possível excluir o último System Admin');

    expect(User::find($admin->id))->not->toBeNull();
});

test('deleting a system admin is allowed when another one still exists', function () {
    ['sysAdmin' => $admin1] = setupUserAdminContext('other1');
    $admin2 = User::factory()->create(['email_verified_at' => now()]);
    SystemAdmin::create(['user_id' => $admin2->id, 'granted_at' => now()]);

    $result = app(UserRemovalService::class)->remove($admin2, $admin1);

    expect($result)->toBe('deleted')
        ->and(SystemAdmin::active()->count())->toBe(1);
});

test('deleting a sole clinic owner is refused — ownership must be transferred first', function () {
    ['clinic' => $clinic, 'sysAdmin' => $admin] = setupUserAdminContext();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($owner->id, ['role' => 'owner']);

    expect(fn () => app(UserRemovalService::class)->remove($owner, $admin))
        ->toThrow(ValidationException::class);

    expect(User::find($owner->id))->not->toBeNull();
});

test('HTTP: destroy requires typing the exact target email as confirmation', function () {
    ['sysAdmin' => $admin] = setupUserAdminContext();
    $target = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($admin)
        ->deleteJson(route('admin.users.destroy', $target->id), ['confirmation' => 'texto-errado'])
        ->assertStatus(422);

    expect(User::find($target->id))->not->toBeNull();
});

test('HTTP: destroy succeeds with the exact email confirmation and returns which path was taken', function () {
    ['sysAdmin' => $admin] = setupUserAdminContext();
    $target = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($admin)
        ->deleteJson(route('admin.users.destroy', $target->id), ['confirmation' => $target->email])
        ->assertOk()
        ->assertJson(['ok' => true, 'result' => 'deleted']);
});

test('HTTP: a system admin cannot delete their own account through this endpoint', function () {
    ['sysAdmin' => $admin] = setupUserAdminContext();

    $this->actingAs($admin)
        ->deleteJson(route('admin.users.destroy', $admin->id), ['confirmation' => $admin->email])
        ->assertForbidden();
});

test('a normal user cannot reach any user-admin endpoint', function () {
    $normal = User::factory()->create(['email_verified_at' => now()]);
    $target = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($normal)->get(route('admin.users'))->assertForbidden();
    $this->actingAs($normal)->postJson(route('admin.users.block', $target->id))->assertForbidden();
    $this->actingAs($normal)->deleteJson(route('admin.users.destroy', $target->id))->assertForbidden();
});
