<?php

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fase B4 — regressão de dois achados feitos durante a auditoria de props
 * Inertia:
 *
 * 1) FinanceController::index() e InventoryController::index() montavam as
 *    queries de Budget/Transaction/InventoryItem sem nenhum
 *    where('clinic_id', ...) explícito — dependiam só do Global Scope
 *    (ClinicScope), que é no-op durante execução via console/testes. Sem
 *    filtro explícito, nenhum teste jamais teria pego um vazamento entre
 *    clínicas nesses dois painéis. Corrigido com filtro explícito, no
 *    mesmo padrão já usado em outros controllers (abort_unless por
 *    clinic_id). ConsultationController::index() recebeu o mesmo reforço.
 *
 * 2) Relações eager-loaded nunca lidas pelo frontend (grep exaustivo +
 *    leitura direta dos componentes Vue): 'appointment' em
 *    ConsultationController::index(), 'treatment' em
 *    AppointmentController::index()/fullscreen(), 'financingProposals' em
 *    FinanceController::index().
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

test('the ziggy route table is no longer duplicated into every Inertia response payload', function () {
    ['user' => $user] = setupB4TenancyContext('-ziggy');

    $html = $this->actingAs($user)->get(route('dashboard'));
    preg_match('/data-page="([^"]+)"/', $html->getContent(), $matches);
    $page = json_decode(html_entity_decode($matches[1]), true);

    expect($page['props'])->not->toHaveKey('ziggy');

    // @routes continua no layout raiz — é ele quem populatea window.Ziggy
    // no client (ZiggyVue não recebe config, lê do global).
    $blade = file_get_contents(resource_path('views/app.blade.php'));
    expect($blade)->toContain('@routes');
});

test('consultations list only exposes patient/professional fields actually used, drops the unused appointment eager load, and never leaks another clinic', function () {
    ['user' => $userA, 'clinic' => $clinicA] = setupB4TenancyContext('-cons-a');
    $treatmentA = Treatment::create([
        'clinic_id' => $clinicA->id, 'nome' => 'Consulta', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 30, 'preco_base' => 100, 'custo_padrao' => 40,
    ]);
    $patientA = Patient::create(['clinic_id' => $clinicA->id, 'nome' => 'Ana', 'sobrenome' => 'ClinicaA', 'status' => 'ativo']);
    $apptA = Appointment::create([
        'clinic_id' => $clinicA->id, 'patient_id' => $patientA->id, 'professional_id' => $userA->id,
        'start' => now()->subHour(), 'end' => now(), 'status' => 'completed', 'treatment_id' => $treatmentA->id,
    ]);
    Consultation::create([
        'clinic_id' => $clinicA->id, 'patient_id' => $patientA->id, 'professional_id' => $userA->id,
        'appointment_id' => $apptA->id, 'status' => 'aguardando', 'check_in_at' => now(),
    ]);

    ['user' => $userB, 'clinic' => $clinicB] = setupB4TenancyContext('-cons-b');
    $treatmentB = Treatment::create([
        'clinic_id' => $clinicB->id, 'nome' => 'Consulta B', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 30, 'preco_base' => 100, 'custo_padrao' => 40,
    ]);
    $patientB = Patient::create(['clinic_id' => $clinicB->id, 'nome' => 'Beto', 'sobrenome' => 'ClinicaB', 'status' => 'ativo']);
    $apptB = Appointment::create([
        'clinic_id' => $clinicB->id, 'patient_id' => $patientB->id, 'professional_id' => $userB->id,
        'start' => now()->subHour(), 'end' => now(), 'status' => 'completed', 'treatment_id' => $treatmentB->id,
    ]);
    Consultation::create([
        'clinic_id' => $clinicB->id, 'patient_id' => $patientB->id, 'professional_id' => $userB->id,
        'appointment_id' => $apptB->id, 'status' => 'aguardando', 'check_in_at' => now(),
    ]);

    session(['current_clinic_id' => $clinicB->id]);

    $queries = [];
    DB::listen(function ($q) use (&$queries) { $queries[] = $q->sql; });

    $response = $this->actingAs($userB)->get(route('consultations.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Consultations/Index')
        ->has('consultations.data', 1)
        ->where('consultations.data.0.patient.nome', 'Beto')
        ->where('consultations.data.0.professional.name', $userB->name)
        ->missing('consultations.data.0.appointment')
    );

    expect(collect($queries)->contains(fn ($sql) => str_contains($sql, 'from "appointments" where "appointments"."id" in')))->toBeFalse();
});
