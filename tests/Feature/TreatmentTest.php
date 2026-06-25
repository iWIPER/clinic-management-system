<?php

use App\Data\DentalTreatmentCatalog;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Treatment;
use App\Models\TreatmentAuditLog;
use App\Models\User;
use App\Services\TreatmentCatalogService;

function setupTreatmentContext(): array
{
    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-treat-' . uniqid(),
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
        'name' => 'Clínica Tratamentos',
        'slug' => 'clinica-trat-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'active',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'owner']);

    session(['current_clinic_id' => $clinic->id]);

    return compact('user', 'clinic');
}

test('seeds dental catalog when clinic has no treatments', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTreatmentContext();

    $expectedCount = count(DentalTreatmentCatalog::items());

    expect(Treatment::where('clinic_id', $clinic->id)->count())->toBe(0);

    $this->actingAs($user)
        ->get(route('treatments.index'))
        ->assertOk();

    expect(Treatment::where('clinic_id', $clinic->id)->count())->toBe($expectedCount);

    $treatment = Treatment::where('clinic_id', $clinic->id)->where('nome', 'Profilaxia Completa')->first();
    expect($treatment)->not->toBeNull()
        ->and($treatment->categoria)->toBe('Dentística')
        ->and($treatment->descricao)->not->toBeEmpty()
        ->and($treatment->duracao_padrao)->toBe(60)
        ->and((float) $treatment->preco_base)->toBe(300.0);
});

test('catalog seed does not overwrite user edited treatments', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTreatmentContext();

    app(TreatmentCatalogService::class)->seedForClinic($clinic, $user->id);

    $treatment = Treatment::where('clinic_id', $clinic->id)
        ->where('catalog_slug', 'dentistica-profilaxia-completa')
        ->first();

    $treatment->update(['preco_base' => 999, 'nome' => 'Profilaxia VIP']);

    app(TreatmentCatalogService::class)->seedForClinic($clinic, $user->id);

    $treatment->refresh();
    expect($treatment->nome)->toBe('Profilaxia VIP')
        ->and((float) $treatment->preco_base)->toBe(999.0);
});

test('hierarchy parent and variations are linked correctly', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTreatmentContext();

    app(TreatmentCatalogService::class)->seedForClinic($clinic, $user->id);

    $grupo = Treatment::where('clinic_id', $clinic->id)
        ->where('catalog_slug', 'dentistica-grupo-resina')
        ->first();

    $variacao = Treatment::where('clinic_id', $clinic->id)
        ->where('catalog_slug', 'dentistica-resina-1-face')
        ->first();

    expect($grupo->tipo)->toBe('grupo')
        ->and($variacao->tipo)->toBe('variacao')
        ->and($variacao->parent_id)->toBe($grupo->id);
});

test('forScheduling excludes inactive and grupo tipo treatments', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTreatmentContext();

    app(TreatmentCatalogService::class)->seedForClinic($clinic, $user->id);

    $grupo = Treatment::where('clinic_id', $clinic->id)->where('tipo', 'grupo')->first();
    $bookable = Treatment::forScheduling()->where('clinic_id', $clinic->id)->pluck('id');

    expect($bookable)->not->toContain($grupo->id);
    expect(Treatment::forScheduling()->where('clinic_id', $clinic->id)->count())->toBeGreaterThan(40);
});

test('treatment show page displays stats breadcrumb and audit', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTreatmentContext();

    app(TreatmentCatalogService::class)->seedForClinic($clinic, $user->id);

    $treatment = Treatment::where('clinic_id', $clinic->id)
        ->where('nome', 'Profilaxia Completa')
        ->first();

    $this->actingAs($user)
        ->get(route('treatments.show', $treatment))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Treatments/Show')
            ->has('stats')
            ->has('auditLogs')
            ->has('breadcrumb')
            ->where('treatment.nome', 'Profilaxia Completa')
            ->where('stats.usage_count', 0)
        );
});

test('can deactivate and reactivate treatment with audit', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTreatmentContext();

    $treatment = Treatment::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Teste Desativação',
        'duracao_padrao' => 30,
        'preco_base' => 100,
        'ativo' => true,
        'tipo' => 'procedimento',
    ]);

    $this->actingAs($user)
        ->post(route('treatments.deactivate', $treatment))
        ->assertRedirect();

    $treatment->refresh();
    expect($treatment->ativo)->toBeFalse()
        ->and($treatment->deactivated_by_id)->toBe($user->id);

    expect(TreatmentAuditLog::where('treatment_id', $treatment->id)->where('action', 'deactivated')->exists())->toBeTrue();

    $this->actingAs($user)
        ->post(route('treatments.reactivate', $treatment))
        ->assertRedirect();

    $treatment->refresh();
    expect($treatment->ativo)->toBeTrue()
        ->and(TreatmentAuditLog::where('treatment_id', $treatment->id)->where('action', 'reactivated')->exists())->toBeTrue();
});

test('blocks deletion when treatment has linked attendances', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTreatmentContext();

    $patient = Patient::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Paciente',
        'sobrenome' => 'Teste',
        'status' => 'ativo',
    ]);

    $treatment = Treatment::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Com Vínculo',
        'duracao_padrao' => 30,
        'preco_base' => 100,
        'ativo' => true,
        'tipo' => 'procedimento',
    ]);

    Appointment::create([
        'clinic_id' => $clinic->id,
        'patient_id' => $patient->id,
        'professional_id' => $user->id,
        'treatment_id' => $treatment->id,
        'start' => now(),
        'end' => now()->addMinutes(30),
        'status' => 'scheduled',
    ]);

    $this->actingAs($user)
        ->from(route('treatments.show', $treatment))
        ->delete(route('treatments.destroy', $treatment))
        ->assertRedirect()
        ->assertSessionHas('error', 'linked_attendances');

    expect(Treatment::find($treatment->id))->not->toBeNull();
});

test('allows deletion when no linked attendances', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTreatmentContext();

    $treatment = Treatment::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Sem Vínculo',
        'duracao_padrao' => 30,
        'preco_base' => 100,
        'ativo' => true,
        'tipo' => 'procedimento',
    ]);

    $this->actingAs($user)
        ->delete(route('treatments.destroy', $treatment))
        ->assertRedirect(route('treatments.index'));

    expect(Treatment::find($treatment->id))->toBeNull();
});

test('inactive and grupo treatments do not appear in appointment create', function () {
    ['user' => $user, 'clinic' => $clinic] = setupTreatmentContext();

    Treatment::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Ativo',
        'ativo' => true,
        'duracao_padrao' => 30,
        'preco_base' => 100,
        'tipo' => 'procedimento',
    ]);

    Treatment::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Inativo',
        'ativo' => false,
        'deactivated_at' => now(),
        'deactivated_by_id' => $user->id,
        'duracao_padrao' => 30,
        'preco_base' => 100,
        'tipo' => 'procedimento',
    ]);

    Treatment::create([
        'clinic_id' => $clinic->id,
        'nome' => 'Grupo Resina',
        'ativo' => true,
        'duracao_padrao' => 0,
        'preco_base' => 0,
        'tipo' => 'grupo',
    ]);

    $response = $this->actingAs($user)->get(route('appointments.create'));

    $response->assertOk();
    $treatments = $response->original->getData()['page']['props']['treatments'] ?? [];
    $names = collect($treatments)->pluck('nome')->all();

    expect($names)->toContain('Ativo')
        ->not->toContain('Inativo')
        ->not->toContain('Grupo Resina');
});