<?php

use App\Models\Chair;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\TaskLabel;
use App\Models\TaskList;
use App\Models\User;

// Fase C4 — testes das Policies centralizadas (PatientPolicy, ClinicPolicy,
// ChairPolicy, TaskListPolicy, TaskLabelPolicy). Cobre os 10 cenários
// pedidos pelo passo 9: cross-tenant nos dois sentidos, 403 sem permissão,
// owner/admin conseguem, professional/staff não ganham poder administrativo
// automaticamente, ações permitidas/proibidas continuam do jeito certo, e
// o ClinicScope continua funcionando junto (não foi removido).

function setupTwoClinicsWithUsers(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-policy-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
        'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);

    $clinicA = Clinic::create([
        'name' => 'Clínica A', 'slug' => 'clinica-a-policy-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $clinicB = Clinic::create([
        'name' => 'Clínica B', 'slug' => 'clinica-b-policy-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);

    $ownerA = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinicA->users()->attach($ownerA->id, ['role' => 'owner']);

    $adminA = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Administrador', 'status' => 'ativo']);
    $clinicA->users()->attach($adminA->id, ['role' => 'admin']);

    $professionalA = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinicA->users()->attach($professionalA->id, ['role' => 'professional']);

    $staffA = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Recepção', 'status' => 'ativo']);
    $clinicA->users()->attach($staffA->id, ['role' => 'staff']);

    $ownerB = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinicB->users()->attach($ownerB->id, ['role' => 'owner']);

    $patientA = Patient::create(['clinic_id' => $clinicA->id, 'nome' => 'Paciente', 'sobrenome' => 'A', 'status' => 'ativo']);
    $patientB = Patient::create(['clinic_id' => $clinicB->id, 'nome' => 'Paciente', 'sobrenome' => 'B', 'status' => 'ativo']);

    return compact('clinicA', 'clinicB', 'ownerA', 'adminA', 'professionalA', 'staffA', 'ownerB', 'patientA', 'patientB');
}

// ── PatientPolicy — tenant isolation nos dois sentidos ──────────────────────

test('a user from Clinic A cannot view a patient from Clinic B (404, ClinicScope hides the route-model binding)', function () {
    ['ownerA' => $ownerA, 'clinicA' => $clinicA, 'patientB' => $patientB] = setupTwoClinicsWithUsers();

    $this->actingAs($ownerA)->withSession(['current_clinic_id' => $clinicA->id])
        ->get(route('patients.show', $patientB))
        ->assertNotFound();
});

test('a user from Clinic B cannot view a patient from Clinic A — isolation is symmetric', function () {
    ['ownerB' => $ownerB, 'clinicB' => $clinicB, 'patientA' => $patientA] = setupTwoClinicsWithUsers();

    $this->actingAs($ownerB)->withSession(['current_clinic_id' => $clinicB->id])
        ->get(route('patients.show', $patientA))
        ->assertNotFound();
});

test('PatientPolicy::view denies directly (not just via ClinicScope) when clinic_id mismatches the active session', function () {
    ['ownerA' => $ownerA, 'clinicA' => $clinicA, 'patientB' => $patientB] = setupTwoClinicsWithUsers();

    $this->actingAs($ownerA);
    session(['current_clinic_id' => $clinicA->id]);

    // Chama a Policy direto, sem passar pelo ClinicScope (que filtraria o
    // registro antes mesmo de chegar aqui) — prova que a segunda camada
    // (a Policy em si) também nega, independentemente do Scope.
    expect($ownerA->can('view', $patientB))->toBeFalse();
});

test('PatientPolicy::view allows a user to view a patient from their own active clinic', function () {
    ['ownerA' => $ownerA, 'clinicA' => $clinicA, 'patientA' => $patientA] = setupTwoClinicsWithUsers();

    $this->actingAs($ownerA);
    session(['current_clinic_id' => $clinicA->id]);

    expect($ownerA->can('view', $patientA))->toBeTrue();
});

test('the real patient show flow continues to work end-to-end for a legitimate same-clinic request', function () {
    ['ownerA' => $ownerA, 'clinicA' => $clinicA, 'patientA' => $patientA] = setupTwoClinicsWithUsers();

    $this->actingAs($ownerA)->withSession(['current_clinic_id' => $clinicA->id])
        ->get(route('patients.show', $patientA))
        ->assertOk();
});

// ── ClinicPolicy — RBAC (owner/admin vs. professional/staff) ────────────────

test('owner can manage the team of their own active clinic', function () {
    ['ownerA' => $ownerA, 'clinicA' => $clinicA] = setupTwoClinicsWithUsers();

    session(['current_clinic_id' => $clinicA->id]);
    expect($ownerA->can('manageTeam', $clinicA))->toBeTrue();
});

test('admin can manage the team of their own active clinic', function () {
    ['adminA' => $adminA, 'clinicA' => $clinicA] = setupTwoClinicsWithUsers();

    session(['current_clinic_id' => $clinicA->id]);
    expect($adminA->can('manageTeam', $clinicA))->toBeTrue();
});

test('professional does not automatically get team-management permission', function () {
    ['professionalA' => $professionalA, 'clinicA' => $clinicA] = setupTwoClinicsWithUsers();

    session(['current_clinic_id' => $clinicA->id]);
    expect($professionalA->can('manageTeam', $clinicA))->toBeFalse();
});

test('staff does not automatically get team-management permission', function () {
    ['staffA' => $staffA, 'clinicA' => $clinicA] = setupTwoClinicsWithUsers();

    session(['current_clinic_id' => $clinicA->id]);
    expect($staffA->can('manageTeam', $clinicA))->toBeFalse();
});

test('an owner of Clinic B cannot manage the team of Clinic A even by passing the model directly', function () {
    ['ownerB' => $ownerB, 'clinicA' => $clinicA, 'clinicB' => $clinicB] = setupTwoClinicsWithUsers();

    // Sessão ativa é a própria clínica B — mas tenta autorizar contra o
    // model da clínica A explicitamente.
    session(['current_clinic_id' => $clinicB->id]);
    expect($ownerB->can('manageTeam', $clinicA))->toBeFalse();
});

test('a staff member gets 403 hitting an owner/admin-only route (updateBusinessHours)', function () {
    ['staffA' => $staffA, 'clinicA' => $clinicA] = setupTwoClinicsWithUsers();

    $this->actingAs($staffA)->withSession(['current_clinic_id' => $clinicA->id])
        ->putJson(route('clinic-settings.agendas.business-hours'), [
            'enforced' => true,
            'days' => collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])
                ->mapWithKeys(fn ($d) => [$d => ['enabled' => false]])->all(),
        ])
        ->assertStatus(403);
});

test('an owner successfully hits the same owner/admin-only route', function () {
    ['ownerA' => $ownerA, 'clinicA' => $clinicA] = setupTwoClinicsWithUsers();

    $this->actingAs($ownerA)->withSession(['current_clinic_id' => $clinicA->id])
        ->putJson(route('clinic-settings.agendas.business-hours'), [
            'enforced' => true,
            'days' => collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])
                ->mapWithKeys(fn ($d) => [$d => ['enabled' => false]])->all(),
        ])
        ->assertOk();
});

// ── ChairPolicy — padrão simples de tenant, representativo de
// ChairPolicy/ClinicalRecordPolicy/BudgetPolicy (mesmo mecanismo) ──────────

test('a user cannot update a chair belonging to another clinic', function () {
    ['ownerA' => $ownerA, 'clinicA' => $clinicA, 'clinicB' => $clinicB] = setupTwoClinicsWithUsers();
    $chairB = Chair::create(['clinic_id' => $clinicB->id, 'name' => 'Cadeira 01', 'color' => '#000']);

    $this->actingAs($ownerA)->withSession(['current_clinic_id' => $clinicA->id])
        ->putJson(route('chairs.update', $chairB), ['name' => 'Hackeada', 'color' => '#fff'])
        ->assertStatus(403);

    expect($chairB->fresh()->name)->toBe('Cadeira 01');
});

test('a user can update a chair belonging to their own clinic', function () {
    ['ownerA' => $ownerA, 'clinicA' => $clinicA] = setupTwoClinicsWithUsers();
    $chairA = Chair::create(['clinic_id' => $clinicA->id, 'name' => 'Cadeira 01', 'color' => '#000']);

    $this->actingAs($ownerA)->withSession(['current_clinic_id' => $clinicA->id])
        ->putJson(route('chairs.update', $chairA), ['name' => 'Renomeada', 'color' => '#fff'])
        ->assertOk();

    expect($chairA->fresh()->name)->toBe('Renomeada');
});

// ── TaskListPolicy — regra extra além do tenant (posse individual) ─────────

test('a custom task list can only be managed by the user who created it, even within the same clinic', function () {
    ['ownerA' => $ownerA, 'adminA' => $adminA, 'clinicA' => $clinicA] = setupTwoClinicsWithUsers();
    $list = TaskList::create(['clinic_id' => $clinicA->id, 'user_id' => $ownerA->id, 'key' => null, 'name' => 'Meu escopo', 'color' => '#000', 'sharing_type' => 'private']);

    // admin é da mesma clínica mas não é quem criou o escopo — mesmo owner/admin
    // não ganha poder sobre escopo customizado alheio (regra de posse, não de role).
    $this->actingAs($adminA)->withSession(['current_clinic_id' => $clinicA->id])
        ->deleteJson(route('task-lists.destroy', $list->id))
        ->assertStatus(403);

    expect($list->fresh())->not->toBeNull();
});

test('the creator of a custom task list can delete it', function () {
    ['ownerA' => $ownerA, 'clinicA' => $clinicA] = setupTwoClinicsWithUsers();
    $list = TaskList::create(['clinic_id' => $clinicA->id, 'user_id' => $ownerA->id, 'key' => null, 'name' => 'Meu escopo', 'color' => '#000', 'sharing_type' => 'private']);

    $this->actingAs($ownerA)->withSession(['current_clinic_id' => $clinicA->id])
        ->deleteJson(route('task-lists.destroy', $list->id))
        ->assertOk();

    expect($list->fresh())->toBeNull();
});

// ── TaskLabelPolicy — clinic_id nulo (etiqueta global) nunca autoriza ──────

test('a global system task label (clinic_id null) can never be deleted by any clinic', function () {
    ['ownerA' => $ownerA, 'clinicA' => $clinicA] = setupTwoClinicsWithUsers();
    $globalLabel = TaskLabel::create(['clinic_id' => null, 'name' => 'Global', 'color' => '#000']);

    $this->actingAs($ownerA)->withSession(['current_clinic_id' => $clinicA->id])
        ->deleteJson(route('task-labels.destroy', $globalLabel->id))
        ->assertStatus(403);

    expect($globalLabel->fresh())->not->toBeNull();
});

test('a clinic can delete its own task label', function () {
    ['ownerA' => $ownerA, 'clinicA' => $clinicA] = setupTwoClinicsWithUsers();
    $label = TaskLabel::create(['clinic_id' => $clinicA->id, 'name' => 'Minha etiqueta', 'color' => '#000']);

    $this->actingAs($ownerA)->withSession(['current_clinic_id' => $clinicA->id])
        ->deleteJson(route('task-labels.destroy', $label->id))
        ->assertOk();

    expect($label->fresh())->toBeNull();
});

// ── ClinicScope continua ativo (a Policy não a substitui, coexistem) ───────
//
// Uma query direta (Patient::all()) não prova nada aqui: ClinicScope::apply()
// retorna cedo sempre que app()->runningInConsole() é true — e isso vale pra
// TODO o processo Pest (php artisan test roda via CLI), não só pra comandos
// artisan de verdade. Esse gap já era conhecido (ver C1.1.1). A forma
// correta de verificar "ClinicScope continua funcionando" nesta suíte é
// confirmar que ele continua registrado nos models (estrutural) — o
// comportamento de filtro em si já é coberto pelas requisições HTTP reais
// acima (que passam pelo kernel completo) e pelos testes de tenant
// isolation pré-existentes no restante do projeto.

test('Patient and Chair still register ClinicScope via BelongsToClinic', function () {
    foreach ([Patient::class, Chair::class] as $modelClass) {
        $traits = class_uses_recursive($modelClass);

        expect($traits)->toHaveKey(\App\Models\Concerns\BelongsToClinic::class);
    }
});

// Achado desta fase, registrado e não alterado (fora do escopo da C4):
// TaskList e TaskLabel NÃO usam BelongsToClinic/ClinicScope — dependem só
// de filtro manual em toda query que os toca. A Policy criada aqui cobre
// os pontos de autorização dos controllers migrados, mas não é um
// substituto de ClinicScope: se alguma query futura contra esses dois
// models esquecer o filtro manual de clinic_id, não há rede de segurança
// automática. Ver relatório final da C4.
test('documents that TaskList and TaskLabel do not use BelongsToClinic (pre-existing gap, not introduced by C4)', function () {
    foreach ([TaskList::class, TaskLabel::class] as $modelClass) {
        $traits = class_uses_recursive($modelClass);

        expect($traits)->not->toHaveKey(\App\Models\Concerns\BelongsToClinic::class);
    }
});
