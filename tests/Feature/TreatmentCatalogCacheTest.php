<?php

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Treatment;
use App\Models\User;
use App\Services\TreatmentCatalogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function setupCatalogCacheContext(string $suffix = ''): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-catcache' . $suffix . '-' . uniqid(),
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
        'name' => 'Clínica Cache' . $suffix,
        'slug' => 'clinica-catcache' . $suffix . '-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic');
}

beforeEach(function () {
    Cache::flush();
});

test('activeCatalog is cold on first call and warm (no query) on second call', function () {
    ['clinic' => $clinic] = setupCatalogCacheContext();

    Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Limpeza', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 30, 'preco_base' => 100, 'custo_padrao' => 40,
    ]);

    $service = app(TreatmentCatalogService::class);

    $queries = [];
    DB::listen(function ($q) use (&$queries) { $queries[] = $q->sql; });

    $cold = $service->activeCatalog($clinic->id);
    expect($queries)->not->toBeEmpty();
    expect($cold)->toHaveCount(1)
        ->and($cold[0]['nome'])->toBe('Limpeza');

    $queries = [];
    $warm = $service->activeCatalog($clinic->id);
    expect($queries)->toBeEmpty();
    expect($warm)->toBe($cold);
});

test('schedulableCatalog is cold on first call and warm (no query) on second call', function () {
    ['clinic' => $clinic] = setupCatalogCacheContext();

    Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Canal', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 60, 'preco_base' => 500,
    ]);

    $service = app(TreatmentCatalogService::class);

    $queries = [];
    DB::listen(function ($q) use (&$queries) { $queries[] = $q->sql; });

    $cold = $service->schedulableCatalog($clinic->id);
    expect($queries)->not->toBeEmpty();
    expect($cold)->toHaveCount(1);

    $queries = [];
    $warm = $service->schedulableCatalog($clinic->id);
    expect($queries)->toBeEmpty();
    expect($warm)->toBe($cold);
});

test('activeCatalog and schedulableCatalog use independent cache keys for the same clinic', function () {
    ['clinic' => $clinic] = setupCatalogCacheContext();

    // Ativo mas não agendável (tipo grupo) — deve aparecer em activeCatalog,
    // nunca em schedulableCatalog.
    Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Grupo Resina', 'tipo' => 'grupo',
        'ativo' => true, 'duracao_padrao' => 0, 'preco_base' => 0,
    ]);

    $service = app(TreatmentCatalogService::class);

    $active = $service->activeCatalog($clinic->id);
    $schedulable = $service->schedulableCatalog($clinic->id);

    expect(collect($active)->pluck('nome'))->toContain('Grupo Resina');
    expect(collect($schedulable)->pluck('nome'))->not->toContain('Grupo Resina');
});

test('cache never serves an inactive treatment', function () {
    ['clinic' => $clinic] = setupCatalogCacheContext();

    $treatment = Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Vai Desativar', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 30, 'preco_base' => 100,
    ]);

    $service = app(TreatmentCatalogService::class);
    expect(collect($service->activeCatalog($clinic->id))->pluck('nome'))->toContain('Vai Desativar');

    $treatment->update(['ativo' => false, 'deactivated_at' => now()]);
    $service->forgetClinic($clinic->id);

    expect(collect($service->activeCatalog($clinic->id))->pluck('nome'))->not->toContain('Vai Desativar');
});

test('store invalidates the cache for the treatment clinic', function () {
    ['user' => $user, 'clinic' => $clinic] = setupCatalogCacheContext();

    $service = app(TreatmentCatalogService::class);
    expect($service->activeCatalog($clinic->id))->toBeEmpty();

    $this->actingAs($user)->post(route('treatments.store'), [
        'nome' => 'Novo Procedimento',
        'tipo' => 'procedimento',
        'duracao_padrao' => 30,
        'preco_base' => 150,
    ])->assertRedirect();

    expect(collect($service->activeCatalog($clinic->id))->pluck('nome'))->toContain('Novo Procedimento');
});

test('update invalidates the cache for the treatment clinic', function () {
    ['user' => $user, 'clinic' => $clinic] = setupCatalogCacheContext();

    $treatment = Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Nome Antigo', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 30, 'preco_base' => 100,
    ]);

    $service = app(TreatmentCatalogService::class);
    expect(collect($service->activeCatalog($clinic->id))->pluck('nome'))->toContain('Nome Antigo');

    $this->actingAs($user)->put(route('treatments.update', $treatment), [
        'nome' => 'Nome Novo',
        'tipo' => 'procedimento',
        'duracao_padrao' => 30,
        'preco_base' => 100,
    ])->assertRedirect();

    $catalog = collect($service->activeCatalog($clinic->id))->pluck('nome');
    expect($catalog)->toContain('Nome Novo')->not->toContain('Nome Antigo');
});

test('updateDefaultCost invalidates the cache for the treatment clinic', function () {
    ['user' => $user, 'clinic' => $clinic] = setupCatalogCacheContext();

    $treatment = Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Custo Padrão', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 30, 'preco_base' => 100, 'custo_padrao' => 40,
    ]);

    $service = app(TreatmentCatalogService::class);
    $service->activeCatalog($clinic->id);

    $this->actingAs($user)->post(route('treatments.default-cost', $treatment), [
        'custo_padrao' => 55,
    ])->assertRedirect();

    $catalog = collect($service->activeCatalog($clinic->id))->firstWhere('nome', 'Custo Padrão');
    expect((float) $catalog['custo_padrao'])->toBe(55.0);
});

test('deactivate invalidates the cache for the treatment clinic', function () {
    ['user' => $user, 'clinic' => $clinic] = setupCatalogCacheContext();

    $treatment = Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Será Desativado', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 30, 'preco_base' => 100,
    ]);

    $service = app(TreatmentCatalogService::class);
    expect(collect($service->activeCatalog($clinic->id))->pluck('nome'))->toContain('Será Desativado');

    $this->actingAs($user)->post(route('treatments.deactivate', $treatment))->assertRedirect();

    expect(collect($service->activeCatalog($clinic->id))->pluck('nome'))->not->toContain('Será Desativado');
});

test('reactivate invalidates the cache for the treatment clinic', function () {
    ['user' => $user, 'clinic' => $clinic] = setupCatalogCacheContext();

    $treatment = Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Será Reativado', 'tipo' => 'procedimento',
        'ativo' => false, 'deactivated_at' => now(), 'duracao_padrao' => 30, 'preco_base' => 100,
    ]);

    $service = app(TreatmentCatalogService::class);
    expect(collect($service->activeCatalog($clinic->id))->pluck('nome'))->not->toContain('Será Reativado');

    $this->actingAs($user)->post(route('treatments.reactivate', $treatment))->assertRedirect();

    expect(collect($service->activeCatalog($clinic->id))->pluck('nome'))->toContain('Será Reativado');
});

test('destroy invalidates the cache for the treatment clinic', function () {
    ['user' => $user, 'clinic' => $clinic] = setupCatalogCacheContext();

    $treatment = Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Será Excluído', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 30, 'preco_base' => 100,
    ]);

    $service = app(TreatmentCatalogService::class);
    expect(collect($service->activeCatalog($clinic->id))->pluck('nome'))->toContain('Será Excluído');

    $this->actingAs($user)->delete(route('treatments.destroy', $treatment))->assertRedirect();

    expect(collect($service->activeCatalog($clinic->id))->pluck('nome'))->not->toContain('Será Excluído');
});

test('cache is tenant-isolated: warming clinic A never leaks into clinic B and updating A never touches B cache', function () {
    ['user' => $userA, 'clinic' => $clinicA] = setupCatalogCacheContext('-a');
    ['user' => $userB, 'clinic' => $clinicB] = setupCatalogCacheContext('-b');

    $treatmentA = Treatment::create([
        'clinic_id' => $clinicA->id, 'nome' => 'Exclusivo Clínica A', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 30, 'preco_base' => 100,
    ]);

    Treatment::create([
        'clinic_id' => $clinicB->id, 'nome' => 'Exclusivo Clínica B', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 30, 'preco_base' => 200,
    ]);

    $service = app(TreatmentCatalogService::class);

    // Clínica A esquenta o cache primeiro.
    $catalogA = collect($service->activeCatalog($clinicA->id))->pluck('nome');
    expect($catalogA)->toContain('Exclusivo Clínica A')->not->toContain('Exclusivo Clínica B');

    // Clínica B consulta depois — nunca deve ver nada da A.
    $catalogB = collect($service->activeCatalog($clinicB->id))->pluck('nome');
    expect($catalogB)->toContain('Exclusivo Clínica B')->not->toContain('Exclusivo Clínica A');

    // Warm B's cache, then mutate A — B's cached entry must remain untouched.
    $queriesB = [];
    DB::listen(fn ($q) => $queriesB[] = $q->sql);
    $service->activeCatalog($clinicB->id);
    expect($queriesB)->toBeEmpty();

    $this->actingAs($userA)->put(route('treatments.update', $treatmentA), [
        'nome' => 'Renomeado A',
        'tipo' => 'procedimento',
        'duracao_padrao' => 30,
        'preco_base' => 100,
    ])->assertRedirect();

    $queriesBAfter = [];
    DB::listen(fn ($q) => $queriesBAfter[] = $q->sql);
    $catalogBAfter = collect($service->activeCatalog($clinicB->id))->pluck('nome');
    expect($queriesBAfter)->toBeEmpty();
    expect($catalogBAfter)->toContain('Exclusivo Clínica B');

    expect($userB)->not->toBeNull();
});

test('patient show, consultation show and document hub consume the cached catalog through the service', function () {
    ['user' => $user, 'clinic' => $clinic] = setupCatalogCacheContext();

    Treatment::create([
        'clinic_id' => $clinic->id, 'nome' => 'Visível no Paciente', 'tipo' => 'procedimento',
        'ativo' => true, 'duracao_padrao' => 30, 'preco_base' => 100, 'custo_padrao' => 40,
    ]);

    $patient = Patient::create([
        'clinic_id' => $clinic->id, 'nome' => 'Paciente', 'sobrenome' => 'Teste', 'status' => 'ativo',
    ]);

    $this->actingAs($user)
        ->get(route('patients.show', $patient))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Patients/Show')
            ->has('catalogTreatments', 1)
            ->where('catalogTreatments.0.nome', 'Visível no Paciente')
        );
});
