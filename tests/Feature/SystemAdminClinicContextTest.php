<?php

use App\Models\AccessLog;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\SystemAdmin;
use App\Models\User;

// Fase "Separação de contexto System Admin x Clínica" — a regra é: login
// de System Admin sempre vai pro Backoffice, current_clinic_id nunca é
// setado automaticamente (login, onboarding, auto-pick), mesmo quando o
// admin tem vínculo real com uma clínica. Isso não significa que ele nunca
// possa acessar uma clínica: um System Admin que também é membro real
// (clinic_user) de uma clínica pode ENTRAR nela explicitamente
// (Admin\ClinicController::enter()) — nunca automaticamente, nunca numa
// clínica da qual não é membro. Ver EnsureCurrentClinic pro gate.

function setupContextTestClinic(string $suffix = '', string $status = 'active'): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-ctx' . $suffix . '-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1,
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Contexto' . $suffix, 'slug' => 'clinica-contexto' . $suffix . '-' . uniqid(),
        'type' => 'odontologia', 'status' => $status, 'plan_id' => $plan->id,
    ]);

    return compact('plan', 'clinic');
}

// makeSystemAdmin() já existe globalmente (Pest compartilha funções entre
// arquivos de teste) — ver tests/Feature/Admin/SystemAdminManagementTest.php.

// System Admin com vínculo REAL em clinic_user — cenário central desta
// fase: o vínculo existe de verdade, mas só vira contexto ativo mediante
// entrada explícita.
function makeSystemAdminWithClinic(string $suffix = '', string $role = 'owner'): array
{
    ['clinic' => $clinic, 'plan' => $plan] = setupContextTestClinic($suffix);
    $admin = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista']);
    $clinic->users()->attach($admin->id, ['role' => $role]);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    return compact('admin', 'clinic', 'plan');
}

// 1. System Admin sem clínica nenhuma entra no Backoffice ao logar.
test('logging in as a system admin with no clinic redirects to the backoffice, not the clinic dashboard', function () {
    $admin = makeSystemAdmin();

    $response = $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

    $response->assertRedirect(route('admin.index'));
    expect(session('current_clinic_id'))->toBeNull();
});

// 2. System Admin COM vínculo real também vai pro Backoffice — a clínica
// nunca é selecionada sozinha, nem tendo pra onde ir.
test('logging in as a system admin who is also a real clinic member still redirects to the backoffice and never auto-selects the clinic', function () {
    ['admin' => $admin] = makeSystemAdminWithClinic('-login-member');

    $response = $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

    $response->assertRedirect(route('admin.index'));
    expect(session('current_clinic_id'))->toBeNull();
    expect(session('current_clinic'))->toBeNull();
});

// 3. currentClinic nunca vem populado no Backoffice, mesmo com vínculo real.
test('the backoffice page never carries a currentClinic prop for a system admin, even one with clinic membership', function () {
    ['admin' => $admin] = makeSystemAdminWithClinic('-props');

    $response = $this->actingAs($admin)->get(route('admin.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Index')
        ->where('currentClinic', null)
    );
});

// 3b. System Admin consegue listar as clínicas às quais realmente
// pertence (auth.myClinics) — vazio sem vínculo, preenchido com vínculo,
// nunca inclui clínicas de terceiros.
test('a system admin can list the clinics they are a real member of via auth.myClinics', function () {
    $adminNoClinic = makeSystemAdmin();

    $this->actingAs($adminNoClinic)->get(route('admin.index'))
        ->assertInertia(fn ($page) => $page->where('auth.myClinics', []));

    ['admin' => $adminWithClinic, 'clinic' => $clinic] = makeSystemAdminWithClinic('-mylist');
    setupContextTestClinic('-mylist-other'); // clínica de terceiro, não deve aparecer

    $this->actingAs($adminWithClinic)->get(route('admin.index'))
        ->assertInertia(fn ($page) => $page
            ->has('auth.myClinics', 1)
            ->where('auth.myClinics.0.id', $clinic->id)
            ->where('auth.myClinics.0.name', $clinic->name)
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

// 5. Acessar rota clínica direto por URL, sem ter entrado explicitamente,
// ainda volta pro Backoffice — mesmo tendo vínculo real com a clínica.
test('a system admin hitting a clinic route directly by URL without entering explicitly is redirected to the backoffice', function () {
    ['admin' => $admin] = makeSystemAdminWithClinic('-directurl');

    $this->actingAs($admin)
        ->get(route('patients.index'))
        ->assertRedirect(route('admin.index'));

    expect(session('current_clinic_id'))->toBeNull();
});

// 6. /dashboard direto também vai pro Backoffice sem entrada explícita.
test('a system admin cannot reach /dashboard directly without entering explicitly first', function () {
    $admin = makeSystemAdmin();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.index'));
});

// 7. Nenhuma rota de onboarding clínico fica acessível por URL direta.
test('a system admin is redirected to the backoffice from every clinic onboarding route, even by direct URL', function (string $routeName) {
    $admin = makeSystemAdmin();

    $this->actingAs($admin)
        ->get(route($routeName))
        ->assertRedirect(route('admin.index'));
})->with([
    'onboarding.choose-role',
    'onboarding.create-clinic',
    'onboarding.complete',
    'onboarding.invite-team',
    'onboarding.join-invite',
]);

// 8. Visitar onboarding por URL direta não planta current_clinic_id sozinho.
test('a system admin never gets current_clinic_id in session just from visiting onboarding routes', function () {
    $admin = makeSystemAdmin();

    $this->actingAs($admin)->get(route('onboarding.create-clinic'));

    expect(session('current_clinic_id'))->toBeNull();
    expect(session('current_clinic'))->toBeNull();
});

// 9. /admin continua protegido contra usuário normal.
test('a regular user cannot reach the backoffice', function () {
    ['clinic' => $clinic] = setupContextTestClinic('-reg');
    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user)->get(route('admin.index'))->assertForbidden();
});

// 10. Usuário normal continua com o auto-pick de sempre no onboarding.
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

// 11. Entrada explícita: System Admin membro real consegue entrar, e
// current_clinic_id/current_clinic ficam corretos.
test('a system admin who is a real member of a clinic can enter it explicitly and current_clinic_id becomes correct', function () {
    ['admin' => $admin, 'clinic' => $clinic] = makeSystemAdminWithClinic('-enter');

    $response = $this->actingAs($admin)->post(route('admin.clinics.enter', $clinic));

    $response->assertRedirect(route('dashboard'));
    expect(session('admin_clinic_context'))->toBeTrue();
    expect(session('current_clinic_id'))->toBe($clinic->id);
    expect(session('current_clinic'))->not->toBeNull();
});

// 12. Depois de entrar, as rotas clínicas funcionam normalmente dentro
// desse contexto.
test('clinic routes work normally after a system admin has explicitly entered that clinic context', function () {
    ['admin' => $admin, 'clinic' => $clinic] = makeSystemAdminWithClinic('-workroutes');

    $this->actingAs($admin)->post(route('admin.clinics.enter', $clinic));

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('patients.index'))->assertOk();
});

// 13. Sem clínica nenhuma vinculada, a tentativa de entrar em qualquer
// clínica existente é bloqueada.
test('a system admin with no clinic membership at all is blocked from entering any clinic', function () {
    $admin = makeSystemAdmin();
    ['clinic' => $clinic] = setupContextTestClinic('-noaccess');

    $this->actingAs($admin)
        ->post(route('admin.clinics.enter', $clinic))
        ->assertForbidden();

    expect(session('current_clinic_id'))->toBeNull();
});

// 14. Membro da Clínica A não consegue entrar na Clínica B — nunca fabrica
// acesso a partir só do privilégio de System Admin.
test('a system admin who belongs only to clinic A is blocked from entering clinic B', function () {
    ['admin' => $admin] = makeSystemAdminWithClinic('-onlyA');
    ['clinic' => $clinicB] = setupContextTestClinic('-onlyB');

    $this->actingAs($admin)
        ->post(route('admin.clinics.enter', $clinicB))
        ->assertForbidden();

    expect(session('current_clinic_id'))->toBeNull();
});

// 15. clinic_id arbitrário/inexistente é bloqueado pelo próprio route
// model binding, antes de qualquer lógica de negócio.
test('a system admin cannot enter a non-existent clinic id', function () {
    $admin = makeSystemAdmin();

    $this->actingAs($admin)
        ->post('/admin/clinicas/999999/entrar')
        ->assertNotFound();
});

// 16. Sair do contexto limpa a sessão e volta pro Backoffice.
test('exiting the clinic context clears the session and returns to the backoffice', function () {
    ['admin' => $admin, 'clinic' => $clinic] = makeSystemAdminWithClinic('-exit');
    $this->actingAs($admin)->post(route('admin.clinics.enter', $clinic));

    $response = $this->actingAs($admin)->post(route('admin.exit-clinic'));

    $response->assertRedirect(route('admin.index'));
    expect(session('admin_clinic_context'))->toBeNull();
    expect(session('current_clinic_id'))->toBeNull();
    expect(session('current_clinic'))->toBeNull();

    // E o Backoffice volta a funcionar sem contexto de clínica nenhum.
    $this->actingAs($admin)->get(route('patients.index'))->assertRedirect(route('admin.index'));
});

// 17. Logout/login não recupera a clínica que o admin tinha entrado antes
// — cada sessão de login começa neutra no Backoffice.
test('after logging out and back in, a system admin does not automatically recover the previously entered clinic', function () {
    ['admin' => $admin, 'clinic' => $clinic] = makeSystemAdminWithClinic('-relogin');
    $this->actingAs($admin)->post(route('admin.clinics.enter', $clinic));
    expect(session('current_clinic_id'))->toBe($clinic->id);

    $this->post('/logout');

    $response = $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

    $response->assertRedirect(route('admin.index'));
    expect(session('current_clinic_id'))->toBeNull();
    expect(session('admin_clinic_context'))->toBeNull();
});

// 18. Tenant isolation continua valendo mesmo depois de uma entrada
// explícita — um paciente de outra clínica continua invisível (404).
test('tenant isolation still holds after a system admin explicitly enters a clinic', function () {
    ['admin' => $admin, 'clinic' => $clinicA] = makeSystemAdminWithClinic('-isoA');
    ['clinic' => $clinicB] = setupContextTestClinic('-isoB');
    $patientB = Patient::create([
        'clinic_id' => $clinicB->id, 'nome' => 'Paciente', 'sobrenome' => 'DaOutraClinica', 'status' => 'ativo',
    ]);

    $this->actingAs($admin)->post(route('admin.clinics.enter', $clinicA));

    $this->actingAs($admin)
        ->get(route('patients.show', $patientB))
        ->assertNotFound();
});

// 19. Policy/RBAC reflete o papel real do admin na clínica escolhida —
// aqui, dono (owner), então manageTeam é permitido.
test('policies respect the admin real pivot role in the clinic entered explicitly', function () {
    ['admin' => $admin, 'clinic' => $clinic] = makeSystemAdminWithClinic('-rbac-owner', 'owner');

    $this->actingAs($admin)->post(route('admin.clinics.enter', $clinic));

    expect($admin->roleInCurrentClinic())->toBe('owner');
    expect($admin->can('manageTeam', $clinic))->toBeTrue();
});

// 19b. Papel menor (staff) na clínica entrada não ganha permissões de
// gestão — o contexto de clínica não eleva privilégio, só abre acesso.
test('a system admin who enters a clinic as staff does not get manageTeam permission', function () {
    ['admin' => $admin, 'clinic' => $clinic] = makeSystemAdminWithClinic('-rbac-staff', 'staff');

    $this->actingAs($admin)->post(route('admin.clinics.enter', $clinic));

    expect($admin->can('manageTeam', $clinic))->toBeFalse();
});

// 20. Entrar e sair ficam registrados em AccessLog, pra auditoria.
test('entering and exiting a clinic context are recorded in the access log', function () {
    ['admin' => $admin, 'clinic' => $clinic] = makeSystemAdminWithClinic('-audit');

    $this->actingAs($admin)->post(route('admin.clinics.enter', $clinic));
    expect(AccessLog::where('action', 'admin_clinic_context_entered')->where('clinic_id', $clinic->id)->exists())->toBeTrue();

    $this->actingAs($admin)->post(route('admin.exit-clinic'));
    expect(AccessLog::where('action', 'admin_clinic_context_exited')->where('clinic_id', $clinic->id)->exists())->toBeTrue();
});

// 21. Entrar e sair do contexto de clínica não faz o aviso de acesso
// privilegiado reaparecer — ele é uma marca no usuário, não algo ligado à
// navegação entre Backoffice e clínica.
test('the admin access notice does not reappear after entering and exiting a clinic context', function () {
    ['admin' => $admin, 'clinic' => $clinic] = makeSystemAdminWithClinic('-notice-clinic');
    $this->actingAs($admin)->post(route('admin.acknowledge-access'))->assertOk();

    $this->actingAs($admin)->post(route('admin.clinics.enter', $clinic));
    $this->actingAs($admin)->post(route('admin.exit-clinic'));

    $this->actingAs($admin)->get(route('admin.index'))
        ->assertInertia(fn ($page) => $page->where('auth.hasAcknowledgedAdminAccess', true));
});

// O aviso de acesso privilegiado aparece (prop hasAcknowledgedAdminAccess
// = false) no primeiro acesso de um System Admin que nunca reconheceu.
test('the admin access notice is shown on the first visit for a system admin who has not acknowledged it yet', function () {
    $admin = makeSystemAdmin();

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertInertia(fn ($page) => $page->where('auth.hasAcknowledgedAdminAccess', false));
});

// Reconhecer o aviso persiste em users.preferences (backend), não em
// sessão — e a mesma requisição seguinte já reflete o estado sem depender
// de nada no client (localStorage/sessionStorage).
test('acknowledging the admin access notice persists it on the user record, not the session', function () {
    $admin = User::factory()->create(['email_verified_at' => now(), 'preferences' => null]);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    $this->actingAs($admin)
        ->post(route('admin.acknowledge-access'))
        ->assertOk();

    expect($admin->fresh()->preferences['admin_notice_acknowledged_at'] ?? null)->not->toBeNull();

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertInertia(fn ($page) => $page->where('auth.hasAcknowledgedAdminAccess', true));
});

// A prova real de que não é sessão: destruir a sessão (logout) e logar de
// novo não traz o aviso de volta — só reaparece se preferences não tiver
// o registro, o que não é mais o caso depois de reconhecido uma vez.
test('the admin access notice never reappears after logging out and logging back in', function () {
    $admin = User::factory()->create(['email_verified_at' => now(), 'preferences' => null]);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    $this->actingAs($admin)->post(route('admin.acknowledge-access'))->assertOk();

    $this->post('/logout');

    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('admin.index'));

    $this->get(route('admin.index'))
        ->assertInertia(fn ($page) => $page->where('auth.hasAcknowledgedAdminAccess', true));
});

// Um simples refresh (nova requisição GET, mesma sessão) também não deve
// mostrar de novo depois de reconhecido.
test('the admin access notice does not reappear on a simple refresh after being acknowledged', function () {
    $admin = User::factory()->create(['email_verified_at' => now(), 'preferences' => null]);
    SystemAdmin::create(['user_id' => $admin->id, 'granted_at' => now()]);

    $this->actingAs($admin)->post(route('admin.acknowledge-access'))->assertOk();

    $this->actingAs($admin)->get(route('admin.index'))
        ->assertInertia(fn ($page) => $page->where('auth.hasAcknowledgedAdminAccess', true));
    $this->actingAs($admin)->get(route('admin.index'))
        ->assertInertia(fn ($page) => $page->where('auth.hasAcknowledgedAdminAccess', true));
});
