<?php

use App\Models\AnamnesisInstance;
use App\Models\AnamnesisTemplate;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Clinic;
use App\Models\ClinicalRecord;
use App\Models\Convenio;
use App\Models\Patient;
use App\Models\PatientOdontogram;
use App\Models\PatientPhoto;
use App\Models\PatientTreatment;
use App\Models\Plan;
use App\Models\Treatment;
use App\Models\User;
use App\Services\PatientHubService;
use Illuminate\Support\Facades\DB;

/**
 * Fase B3 — regressão da otimização de PatientOdontogramController /
 * PatientHubService::buildForOdontogram(). Medido: a página do Odontograma
 * chamava $hubService->build() completo (ensureRelations com 17 relações +
 * 14 métodos de agregação), mas Odontogram.vue só lê hub.timeline e
 * hub.treatments (confirmado por leitura direta do componente, não por
 * suposição). buildForOdontogram() carrega só as 7 relações que esses dois
 * métodos realmente acessam. Medido no cenário deste arquivo: 23 → 11
 * queries do hub (34 → 22 na página inteira), mesmo payload de dados nas
 * duas chaves usadas.
 */
function setupOdontogramHubContext(string $suffix = ''): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-odonto-hub' . $suffix . '-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);
    $clinic = Clinic::create([
        'name' => 'Clínica Odonto Hub' . $suffix, 'slug' => 'clinica-odonto-hub' . $suffix . '-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);
    $dentist = User::factory()->create(['email_verified_at' => now(), 'job_title' => 'Dentista', 'status' => 'ativo']);
    $clinic->users()->attach($dentist->id, ['role' => 'owner']);
    $convenio = Convenio::create(['clinic_id' => $clinic->id, 'nome' => 'Particular', 'ativo' => true]);
    $treatment = Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Restauração', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 30, 'preco_base' => 150, 'custo_padrao' => 60,
    ]);

    session(['current_clinic_id' => $clinic->id]);

    $patient = Patient::create([
        'clinic_id' => $clinic->id, 'nome' => 'Paciente', 'sobrenome' => 'Odontograma' . $suffix,
        'status' => 'ativo', 'nascimento' => '1990-05-10',
    ]);

    $teethData = [];
    foreach (['11', '12', '21', '22', '36', '46'] as $tooth) {
        $teethData[$tooth] = ['status' => 'restaurado', 'notes' => 'Dente ' . $tooth];
    }
    PatientOdontogram::create([
        'clinic_id' => $clinic->id, 'patient_id' => $patient->id,
        'teeth_data' => $teethData, 'updated_by_id' => $dentist->id,
    ]);

    foreach (range(1, 4) as $i) {
        ClinicalRecord::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'professional_id' => $dentist->id,
            'procedure_name' => 'Procedimento ' . $i, 'procedure_category' => 'Dentística',
            'status' => 'concluido', 'started_at' => now()->subDays($i)->subHour(),
            'finished_at' => now()->subDays($i), 'price' => 120 + $i,
        ]);
    }

    $template = AnamnesisTemplate::first() ?? AnamnesisTemplate::create([
        'name' => 'Anamnese Geral', 'slug' => 'anamnese-geral-hub-' . uniqid(), 'version' => 1,
        'is_system' => false, 'is_active' => true,
    ]);
    foreach (range(1, 2) as $i) {
        AnamnesisInstance::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'template_id' => $template->id,
            'template_name' => $template->name, 'professional_id' => $dentist->id,
            'status' => 'concluido', 'completed_at' => now()->subDays($i),
        ]);
    }

    foreach (['Radiografias', 'Radiografias', 'Documentação'] as $i => $categoria) {
        PatientPhoto::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'categoria' => $categoria,
            'subcategoria' => $categoria === 'Documentação' ? 'Termo de Consentimento' : null,
            'filename' => 'foto-' . $i . '.jpg', 'mime_type' => 'image/jpeg', 'status' => 'active',
            'taken_at' => now()->subDays($i), 'dente' => $i === 0 ? '11' : null,
        ]);
    }

    foreach (range(1, 2) as $i) {
        $budget = Budget::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id,
            'status' => 'aprovado', 'total' => 300, 'notes' => 'Orçamento ' . $i,
        ]);
        BudgetItem::create([
            'budget_id' => $budget->id, 'treatment_id' => $treatment->id,
            'descricao' => $treatment->nome, 'quantidade' => 1,
            'preco_unitario' => 150, 'total' => 150,
        ]);
    }

    foreach (['11', '21', '36'] as $tooth) {
        PatientTreatment::create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'treatment_id' => $treatment->id,
            'professional_id' => $dentist->id, 'convenio_id' => $convenio->id,
            'procedure_name' => $treatment->nome, 'budget_code' => PatientTreatment::nextBudgetCode($clinic->id, now()),
            'tooth' => $tooth, 'value_charged' => 150, 'cost' => 60,
            'status' => 'concluido', 'treatment_date' => now()->subDays(3), 'completed_at' => now()->subDays(2),
        ]);
    }

    return compact('dentist', 'clinic', 'patient', 'convenio', 'treatment');
}

test('the odontogram page query count stays within a tight bound (regression against the old build() N+1)', function () {
    ['dentist' => $dentist, 'patient' => $patient] = setupOdontogramHubContext();

    $queries = [];
    DB::listen(function ($q) use (&$queries) { $queries[] = $q->sql; });

    $this->actingAs($dentist)->get(route('patients.odontogram', $patient))->assertOk();

    // Antes da B3 (hub = build() completo): 34 queries no total. Depois
    // (hub = buildForOdontogram()): 22. Damos folga (28) para não quebrar
    // por mudanças incidentais não relacionadas ao hub, mas continuamos
    // muito abaixo do patamar antigo — o que importa é nunca voltar a subir
    // proporcionalmente às 12 chaves não usadas de build().
    expect(count($queries))->toBeLessThanOrEqual(28);
});

test('buildForOdontogram() returns exactly the same timeline and treatments as the full build()', function () {
    ['patient' => $patient] = setupOdontogramHubContext();

    $service = app(PatientHubService::class);

    $full = $service->build(Patient::find($patient->id));
    $lean = $service->buildForOdontogram(Patient::find($patient->id));

    expect($lean)->toHaveKeys(['timeline', 'treatments'])
        ->and($lean['timeline'])->toBe($full['timeline'])
        ->and($lean['treatments'])->toBe($full['treatments'])
        ->and($lean['timeline'])->not->toBeEmpty()
        ->and($lean['treatments'])->not->toBeEmpty();
});

test('the odontogram page only exposes hub.timeline and hub.treatments, not the rest of build()', function () {
    ['dentist' => $dentist, 'patient' => $patient] = setupOdontogramHubContext();

    $response = $this->actingAs($dentist)->get(route('patients.odontogram', $patient));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Patients/Odontogram')
        ->has('hub.timeline')
        ->has('hub.treatments')
        ->missing('hub.badges')
        ->missing('hub.summary')
        ->missing('hub.professionals')
        ->missing('hub.documents')
        ->missing('hub.aiInsights')
        ->missing('hub.toothHistory')
    );
});

test('the odontogram, tooth statuses and per-tooth treatments are unaffected by the hub optimization', function () {
    ['dentist' => $dentist, 'patient' => $patient] = setupOdontogramHubContext();

    $response = $this->actingAs($dentist)->get(route('patients.odontogram', $patient));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Patients/Odontogram')
        ->where('odontogram.teeth_data.11.status', 'restaurado')
        ->has('toothStatuses')
        ->has('treatmentsByTooth.11')
        ->has('treatmentsByTooth.21')
        ->has('treatmentsByTooth.36')
    );
});

test('a user cannot open another clinic patient odontogram page', function () {
    ['patient' => $patientA] = setupOdontogramHubContext('-a');
    ['dentist' => $dentistB] = setupOdontogramHubContext('-b');

    $this->actingAs($dentistB)
        ->get(route('patients.odontogram', $patientA))
        ->assertNotFound();
});

test('another clinic treatments, budgets or clinical records never appear in the odontogram hub timeline/treatments', function () {
    ['patient' => $patientA] = setupOdontogramHubContext('-iso-a');
    ['dentist' => $dentistB, 'patient' => $patientB] = setupOdontogramHubContext('-iso-b');

    $response = $this->actingAs($dentistB)->get(route('patients.odontogram', $patientB));

    $response->assertOk();
    $page = $response->viewData('page')['props'];

    $treatmentNames = collect($page['hub']['treatments'])->pluck('name')->filter();
    expect($treatmentNames)->each(fn ($name) => $name->not->toContain('-iso-a'));

    $timelineDetails = collect($page['hub']['timeline'])->pluck('detail')->filter();
    expect($timelineDetails->contains(fn ($d) => str_contains((string) $d, $patientA->sobrenome)))->toBeFalse();
});
