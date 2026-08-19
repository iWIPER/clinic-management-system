<?php

use App\Models\Clinic;
use App\Models\InventoryItem;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\User;

/**
 * Fase B4 — achado feito durante a auditoria de props Inertia:
 * FinanceController::index() e InventoryController::index() montavam as
 * queries de Budget/Transaction/InventoryItem sem nenhum
 * where('clinic_id', ...) explícito — dependiam só do Global Scope
 * (ClinicScope), que é no-op durante execução via console/testes. Sem
 * filtro explícito, nenhum teste jamais teria pego um vazamento entre
 * clínicas nesses dois painéis. Corrigido com filtro explícito, no mesmo
 * padrão já usado em outros controllers.
 */
function setupB4TenancyContext(string $suffix = ''): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-b4' . $suffix . '-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica B4' . $suffix, 'slug' => 'clinica-b4' . $suffix . '-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $user = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($user->id, ['role' => 'owner']);
    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic');
}

test('finance dashboard never shows budgets, transactions or totals from another clinic', function () {
    ['user' => $userA, 'clinic' => $clinicA] = setupB4TenancyContext('-fin-a');
    Transaction::create([
        'clinic_id' => $clinicA->id, 'tipo' => 'receita', 'valor' => 999, 'categoria' => 'procedimento',
        'descricao' => 'Receita da clínica A', 'status' => 'pago',
    ]);

    ['user' => $userB, 'clinic' => $clinicB] = setupB4TenancyContext('-fin-b');
    Transaction::create([
        'clinic_id' => $clinicB->id, 'tipo' => 'receita', 'valor' => 50, 'categoria' => 'procedimento',
        'descricao' => 'Receita da clínica B', 'status' => 'pago',
    ]);

    session(['current_clinic_id' => $clinicB->id]);

    $response = $this->actingAs($userB)->get(route('finance.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Finance/Index')
        ->where('totalReceita', 50)
        ->has('transactions', 1)
        ->where('transactions.0.descricao', 'Receita da clínica B')
    );
});

test('inventory list never shows items from another clinic', function () {
    ['clinic' => $clinicA] = setupB4TenancyContext('-inv-a');
    InventoryItem::create(['clinic_id' => $clinicA->id, 'nome' => 'Item da clínica A', 'quantidade' => 10]);

    ['user' => $userB, 'clinic' => $clinicB] = setupB4TenancyContext('-inv-b');
    InventoryItem::create(['clinic_id' => $clinicB->id, 'nome' => 'Item da clínica B', 'quantidade' => 5]);

    session(['current_clinic_id' => $clinicB->id]);

    $response = $this->actingAs($userB)->get(route('inventory.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Inventory/Index')
        ->has('items.data', 1)
        ->where('items.data.0.nome', 'Item da clínica B')
    );
});
