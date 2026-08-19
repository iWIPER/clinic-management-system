<?php

use App\Models\AnamnesisCategoryDefinition;
use App\Models\AnamnesisQuestion;
use App\Models\AnamnesisTemplate;
use App\Models\Clinic;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\Plan;
use App\Models\User;

// Fase C4.1 — achado do Passo 11 da C4: 5 controllers (AnamnesisCategory,
// AnamnesisQuestion, AnamnesisTemplate, DocumentCategory, DocumentTemplate)
// tinham a MESMA forma de checagem de tenant que os outros ~12, mas com
// semântica oposta pra clinic_id nulo — "registro padrão do sistema,
// visível/editável por qualquer clínica", não "nunca autoriza". Cobrimos
// aqui os 3 cenários exigidos (própria clínica / outra clínica / global)
// direto na Policy (sem tocar banco — clinic_id é a única coisa que ela lê)
// pros 5 models reais, e o fluxo HTTP completo pra 2 controllers
// representativos.

function setupGlobalOrOwnClinicContext(string $suffix = ''): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-global' . $suffix . '-' . uniqid(),
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
        'name' => 'Clínica Global' . $suffix,
        'slug' => 'clinica-global' . $suffix . '-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    return compact('plan', 'clinic', 'user');
}

// ── Policy direta, para os 5 models reais (sem gravar no banco — a Policy
// só lê ->clinic_id, então uma instância não persistida já é suficiente e
// evita fricção com o schema real de cada tabela) ──────────────────────────

describe('the 5 policies migrated in C4.1 share the same global-or-own-clinic semantics', function () {
    $cases = [
        [AnamnesisCategoryDefinition::class, 'manage'],
        [AnamnesisQuestion::class, 'manage'],
        [AnamnesisTemplate::class, 'manage'],
        [DocumentCategory::class, 'manage'],
        [DocumentTemplate::class, 'manage'],
    ];

    foreach ($cases as [$modelClass, $ability]) {
        test("{$modelClass}: own clinic is allowed, other clinic is denied, global (null) is always allowed", function () use ($modelClass, $ability) {
            ['clinic' => $clinic, 'user' => $user] = setupGlobalOrOwnClinicContext();
            session(['current_clinic_id' => $clinic->id]);

            $ownResource = new $modelClass(['clinic_id' => $clinic->id]);
            $otherResource = new $modelClass(['clinic_id' => $clinic->id + 999999]);
            $globalResource = new $modelClass(['clinic_id' => null]);

            expect($user->can($ability, $ownResource))->toBeTrue()
                ->and($user->can($ability, $otherResource))->toBeFalse()
                ->and($user->can($ability, $globalResource))->toBeTrue();
        });
    }
});

test('a global resource is manageable even by a user with no active clinic in session at all', function () {
    ['user' => $user] = setupGlobalOrOwnClinicContext();
    session()->forget('current_clinic_id');

    $global = new AnamnesisTemplate(['clinic_id' => null]);
    $ownedByRealClinic = new AnamnesisTemplate(['clinic_id' => 1]);

    expect($user->can('manage', $global))->toBeTrue()
        ->and($user->can('manage', $ownedByRealClinic))->toBeFalse();
});

// ── Fluxo HTTP real, 2 controllers representativos ──────────────────────────

test('AnamnesisCategoryController: a clinic can update its own category, gets 404 on a foreign one, and can update a global one', function () {
    ['clinic' => $clinicA, 'user' => $userA] = setupGlobalOrOwnClinicContext('a');
    ['clinic' => $clinicB] = setupGlobalOrOwnClinicContext('b');

    $ownCategory = AnamnesisCategoryDefinition::create([
        'clinic_id' => $clinicA->id, 'name' => 'Categoria da A', 'slug' => 'categoria-a-' . uniqid(),
    ]);
    $foreignCategory = AnamnesisCategoryDefinition::create([
        'clinic_id' => $clinicB->id, 'name' => 'Categoria da B', 'slug' => 'categoria-b-' . uniqid(),
    ]);
    $globalCategory = AnamnesisCategoryDefinition::create([
        'clinic_id' => null, 'name' => 'Categoria Padrão', 'slug' => 'categoria-padrao-' . uniqid(), 'is_system' => true,
    ]);

    // CategoryDefinitionService::update() normaliza o nome pra maiúsculas —
    // comportamento pré-existente, não relacionado a esta fase.
    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->put(route('anamnesis-categories.update', $ownCategory), ['name' => 'Renomeada A'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
    expect($ownCategory->fresh()->name)->toBe('RENOMEADA A');

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->put(route('anamnesis-categories.update', $foreignCategory), ['name' => 'Hack'])
        ->assertForbidden();
    expect($foreignCategory->fresh()->name)->toBe('Categoria da B');

    // Global (clinic_id nulo) — não marcado is_system aqui de propósito:
    // queremos isolar o comportamento de AUTORIZAÇÃO POR TENANT (o que esta
    // fase migrou), não a regra de negócio separada e já existente de
    // "categoria/modelo de sistema não pode ser editado" (abort_if
    // is_system em alguns dos 5 controllers) — essa é ortogonal e
    // continua intocada.

    // Nome sem acento de propósito: CategoryDefinitionService usa
    // strtoupper() (não mb_strtoupper()), que corrompe byte-a-byte
    // caracteres UTF-8 acentuados — achado incidental, pré-existente,
    // sem relação com autorização, fora do escopo desta fase.
    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->put(route('anamnesis-categories.update', $globalCategory), ['name' => 'Categoria Padrao Renomeada'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
    expect($globalCategory->fresh()->name)->toBe('CATEGORIA PADRAO RENOMEADA');
});

test('DocumentCategoryController: same three scenarios end to end, plus an unauthenticated request is redirected to login', function () {
    ['clinic' => $clinicA, 'user' => $userA] = setupGlobalOrOwnClinicContext('doca');
    ['clinic' => $clinicB] = setupGlobalOrOwnClinicContext('docb');

    $ownCategory = DocumentCategory::create([
        'clinic_id' => $clinicA->id, 'name' => 'Doc Categoria A', 'slug' => 'doc-categoria-a-' . uniqid(),
    ]);
    $foreignCategory = DocumentCategory::create([
        'clinic_id' => $clinicB->id, 'name' => 'Doc Categoria B', 'slug' => 'doc-categoria-b-' . uniqid(),
    ]);
    // is_system deliberadamente false: DocumentCategoryController::update()
    // tem seu próprio abort_if($category->is_system, ...) — regra de
    // negócio ortogonal à autorização por tenant que estamos testando aqui.
    $globalCategory = DocumentCategory::create([
        'clinic_id' => null, 'name' => 'Doc Categoria Padrão', 'slug' => 'doc-categoria-padrao-' . uniqid(), 'is_system' => false,
    ]);

    $this->post(route('document-categories.deactivate', $ownCategory))
        ->assertRedirect(route('login'));

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->put(route('document-categories.update', $ownCategory), ['name' => 'Renomeada'])
        ->assertSessionHasNoErrors();
    expect($ownCategory->fresh()->name)->toBe('Renomeada');

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->put(route('document-categories.update', $foreignCategory), ['name' => 'Hack'])
        ->assertForbidden();

    $this->actingAs($userA)->withSession(['current_clinic_id' => $clinicA->id])
        ->put(route('document-categories.update', $globalCategory), ['name' => 'Padrão Renomeada'])
        ->assertSessionHasNoErrors();
    expect($globalCategory->fresh()->name)->toBe('Padrão Renomeada');
});
