<?php

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\SystemAdmin;
use App\Models\User;

// Fase "Separação de contexto System Admin x Clínica" — antes desta fase,
// login sempre montava a sessão de clínica e mandava pro /dashboard, mesmo
// pra quem também é System Admin (AuthenticatedSessionController::store()
// nunca checava isSystemAdmin()). Como o usuário já tinha vínculo real com
// uma clínica, EnsureCurrentClinic deixava passar normalmente — o
// resultado era entrar direto no shell clínico (sidebar completa) tendo
// que navegar manualmente até o Backoffice depois. As rotas /admin/* já
// eram corretamente protegidas só por 'system-admin' (não dependiam de
// clinic middleware) — o problema real era o destino padrão pós-login e a
// ausência de um gate explícito nas rotas clínicas.

function setupContextTestClinic(string $suffix = ''): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-ctx' . $suffix . '-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Contexto' . $suffix, 'slug' => 'clinica-contexto' . $suffix . '-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);

    return compact('plan', 'clinic');
}

function makeSystemAdminWithClinic(): array
{
    ['clinic' => $clinic] = setupContextTestClinic('-sa');
    $admin = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista']);
    $clinic->users()->attach($admin->id, ['role' => 'owner']);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    return compact('admin', 'clinic');
}

// 1/3. System Admin entra no Backoffice ao logar (não no /dashboard).
test('logging in as a system admin redirects to the backoffice, not the clinic dashboard', function () {
    ['admin' => $admin] = makeSystemAdminWithClinic();

    $response = $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

    $response->assertRedirect(route('admin.index'));
    expect(session('current_clinic_id'))->toBeNull();
});

// 2. Sidebar clínica não aparece no Backoffice — proxy no backend:
// currentClinic (prop que dirige a UI clínica) vem nulo enquanto não há
// entrada explícita, mesmo o admin tendo clínica de verdade.
test('the backoffice page never carries a currentClinic prop for a system admin who has not explicitly entered a clinic', function () {
    ['admin' => $admin] = makeSystemAdminWithClinic();

    $response = $this->actingAs($admin)->get(route('admin.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Index')
        ->where('currentClinic', null)
    );
});

// 4. Usuário normal (não System Admin) continua entrando no contexto clínico.
test('logging in as a regular clinic user still redirects to the clinic dashboard', function () {
    ['clinic' => $clinic] = setupContextTestClinic('-normal');
    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $response->assertRedirect(route('dashboard'));
    expect(session('current_clinic_id'))->toBe($clinic->id);
});

// A System Admin sem nenhum vínculo de clínica também vai pro Backoffice
// (não quebra o fluxo já coberto por SystemAdminAccessTest.php).
test('logging in as a system admin with no clinic at all still redirects to the backoffice', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    $response = $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

    $response->assertRedirect(route('admin.index'));
});

// System Admin tentando acessar uma rota clínica direto por URL, sem
// nunca ter passado por "Acessar clínica", é mandado de volta pro
// Backoffice — não é só esconder a sidebar, é um gate real de middleware.
test('a system admin hitting a clinic route directly by URL, without explicit access, is redirected to the backoffice', function () {
    ['admin' => $admin] = makeSystemAdminWithClinic();

    $this->actingAs($admin)
        ->get(route('patients.index'))
        ->assertRedirect(route('admin.index'));
});

// 5. System Admin com clínica continua tendo o vínculo real — nada nesta
// fase mexe em clinic_user.
test('a system admin who owns a clinic keeps that real clinic membership untouched throughout', function () {
    ['admin' => $admin, 'clinic' => $clinic] = makeSystemAdminWithClinic();

    $this->actingAs($admin)->post(route('admin.enter-clinic'));
    $this->actingAs($admin)->post(route('admin.exit-clinic'));

    expect($admin->clinics()->where('clinics.id', $clinic->id)->exists())->toBeTrue();
    expect($admin->fresh()->clinics()->wherePivot('role', 'owner')->where('clinics.id', $clinic->id)->exists())->toBeTrue();
});

// 6. Acesso explícito à clínica funciona: "Acessar clínica" monta a
// sessão de clínica e libera as rotas clínicas de verdade.
test('explicitly entering the clinic context via "Acessar clínica" grants access to clinic routes', function () {
    ['admin' => $admin, 'clinic' => $clinic] = makeSystemAdminWithClinic();

    $this->actingAs($admin)
        ->post(route('admin.enter-clinic'))
        ->assertRedirect(route('dashboard'));

    expect(session('admin_clinic_context'))->toBeTrue();
    expect(session('current_clinic_id'))->toBe($clinic->id);

    $this->actingAs($admin)
        ->get(route('patients.index'))
        ->assertOk();
});

// Sem nenhuma clínica vinculada, "Acessar clínica" não fabrica acesso a
// nenhuma clínica arbitrária — 404 explícito.
test('"Acessar clínica" fails clearly for a system admin with no clinic membership at all', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    $this->actingAs($admin)->post(route('admin.enter-clinic'))->assertNotFound();
});

// 7. "Voltar ao Backoffice" funciona: limpa a sessão de clínica e o flag,
// e uma rota clínica volta a exigir acesso explícito depois disso.
test('"Voltar ao Backoffice" clears the clinic session and blocks clinic routes again until re-entered', function () {
    ['admin' => $admin] = makeSystemAdminWithClinic();

    $this->actingAs($admin)->post(route('admin.enter-clinic'));

    $this->actingAs($admin)
        ->post(route('admin.exit-clinic'))
        ->assertRedirect(route('admin.index'));

    expect(session('admin_clinic_context'))->toBeNull();
    expect(session('current_clinic_id'))->toBeNull();

    $this->actingAs($admin)
        ->get(route('patients.index'))
        ->assertRedirect(route('admin.index'));
});

// 8. /admin continua protegido contra usuário normal — regressão rápida
// (cobertura completa já existe em Admin/SystemAdminAccessTest.php).
test('a regular user still cannot reach the backoffice nor the clinic-context switch endpoints', function () {
    ['clinic' => $clinic] = setupContextTestClinic('-reg');
    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user)->get(route('admin.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.enter-clinic'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.exit-clinic'))->assertForbidden();
});

// 9. Tenant isolation permanece intacto: um System Admin em visita
// explícita à Clínica A continua sem enxergar dados da Clínica B — o
// acesso explícito não vira acesso cross-tenant irrestrito.
test('a system admin who explicitly entered clinic A still cannot see a patient belonging to clinic B', function () {
    ['admin' => $admin] = makeSystemAdminWithClinic();
    ['clinic' => $clinicB] = setupContextTestClinic('-other');

    $patientB = Patient::create([
        'clinic_id' => $clinicB->id, 'nome' => 'Paciente', 'sobrenome' => 'DaClinicaB', 'status' => 'ativo',
    ]);

    $this->actingAs($admin)->post(route('admin.enter-clinic'));

    $this->actingAs($admin)
        ->get(route('patients.show', $patientB))
        ->assertStatus(404);
});

// Gap encontrado na auditoria: o gate de EnsureCurrentClinic só cobria
// mode 'strict' — rotas 'clinic:onboarding' passavam direto pro bloco de
// auto-pick (incondicional a mode), gravando current_clinic_id/
// current_clinic na sessão de um System Admin só por ele visitar
// /onboarding/* por URL, sem nenhuma ação explícita. Corrigido: o
// auto-pick agora nunca roda pra System Admin, em nenhum mode.
test('visiting an onboarding-mode route never auto-picks a clinic into session for a system admin, even with a real clinic membership', function () {
    ['admin' => $admin, 'clinic' => $clinic] = makeSystemAdminWithClinic();

    $response = $this->actingAs($admin)->get(route('onboarding.choose-role'));

    // A rota de onboarding em si continua acessível (não é bloqueada) —
    // só não pode selecionar clínica sozinha.
    $response->assertOk();

    expect(session('current_clinic_id'))->toBeNull();
    expect(session('current_clinic'))->toBeNull();
    expect(session('admin_clinic_context'))->toBeNull();

    // O fluxo explícito continua funcionando normalmente depois disso —
    // a visita ao onboarding não deixou nenhum estado que atrapalhe.
    $this->actingAs($admin)
        ->post(route('admin.enter-clinic'))
        ->assertRedirect(route('dashboard'));

    expect(session('current_clinic_id'))->toBe($clinic->id);
    expect(session('admin_clinic_context'))->toBeTrue();
});

// Usuário normal continua com o comportamento de sempre no onboarding —
// o auto-pick para ele é exatamente o mesmo de antes desta correção.
test('a regular user visiting an onboarding-mode route keeps auto-picking their clinic into session as before', function () {
    ['clinic' => $clinic] = setupContextTestClinic('-onboard-normal');
    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    $response = $this->actingAs($user)
        ->withSession([]) // sem clínica ativa, como um login fresco
        ->get(route('onboarding.choose-role'));

    $response->assertOk();
    expect(session('current_clinic_id'))->toBe($clinic->id);
    expect(session('current_clinic'))->not->toBeNull();
});
