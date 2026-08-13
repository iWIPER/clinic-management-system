<?php

use App\Models\Chair;
use App\Models\Clinic;
use App\Models\Plan;
use App\Models\User;

function makeOnboardingPlan(): Plan
{
    return Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-onboarding-' . uniqid(),
        'is_free' => true,
        'price_monthly_cents' => 0,
        'price_yearly_cents' => 0,
        'max_clinics' => 1,
        'max_patients' => 100,
        'max_users' => 5,
        'storage_gb' => 1,
        'features' => [],
    ]);
}

function onboardingPayload(Plan $plan, array $overrides = []): array
{
    return array_merge([
        'name' => 'Clínica Onboarding Teste',
        'type' => 'odontologia',
        'cnpj' => '',
        'plan_slug' => $plan->slug,
        'onboarding_stage' => 'under_1y',
        'onboarding_current_system' => 'paper_or_calendar',
        'chairs_count' => 2,
    ], $overrides);
}

test('the first card still branches to clinic creation for owners', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post(route('onboarding.choose-role'), ['role_type' => 'owner'])
        ->assertRedirect(route('onboarding.create-clinic'));
});

test('the first card still branches to the invite flow for guests', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post(route('onboarding.choose-role'), ['role_type' => 'guest'])
        ->assertRedirect(route('onboarding.join-invite'));
});

test('finishing the wizard creates the clinic with the onboarding answers and redirects to the completion screen', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $plan = makeOnboardingPlan();

    $this->actingAs($user)
        ->post(route('onboarding.create-clinic'), onboardingPayload($plan, ['chairs_count' => 3]))
        ->assertRedirect(route('onboarding.complete'));

    $clinic = Clinic::where('name', 'Clínica Onboarding Teste')->firstOrFail();
    expect($clinic->onboarding_stage)->toBe('under_1y')
        ->and($clinic->onboarding_current_system)->toBe('paper_or_calendar')
        ->and($clinic->users()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('choosing N chairs during onboarding creates exactly N sequentially named chairs', function (int $chairsCount) {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $plan = makeOnboardingPlan();

    $this->actingAs($user)
        ->post(route('onboarding.create-clinic'), onboardingPayload($plan, [
            'name' => "Clínica {$chairsCount} Cadeiras",
            'chairs_count' => $chairsCount,
        ]))
        ->assertRedirect(route('onboarding.complete'));

    $clinic = Clinic::where('name', "Clínica {$chairsCount} Cadeiras")->firstOrFail();
    $names = Chair::where('clinic_id', $clinic->id)->orderBy('id')->pluck('name')->all();

    expect($names)->toHaveCount($chairsCount);
    foreach (range(1, $chairsCount) as $i) {
        expect($names[$i - 1])->toBe(sprintf('Cadeira %02d', $i));
    }
})->with([1, 2, 3, 4, 5, 6]);

test('the wizard rejects a chairs_count beyond the centralized limit of 6', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $plan = makeOnboardingPlan();

    $this->actingAs($user)
        ->post(route('onboarding.create-clinic'), onboardingPayload($plan, ['chairs_count' => 7]))
        ->assertSessionHasErrors(['chairs_count']);

    expect(Clinic::where('name', 'Clínica Onboarding Teste')->exists())->toBeFalse();
});

test('the completion screen reflects the number of chairs actually created', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $plan = makeOnboardingPlan();

    $this->actingAs($user)
        ->post(route('onboarding.create-clinic'), onboardingPayload($plan, ['chairs_count' => 2]));

    $this->actingAs($user)
        ->get(route('onboarding.complete'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Complete')
            ->where('chairsCount', 2)
            ->where('clinicName', 'Clínica Onboarding Teste'));
});

test('the invite-team screen is still reachable after onboarding, unchanged', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $plan = makeOnboardingPlan();

    $this->actingAs($user)
        ->post(route('onboarding.create-clinic'), onboardingPayload($plan));

    $this->actingAs($user)
        ->get(route('onboarding.invite-team'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Onboarding/InviteTeam'));
});

test('the wizard rejects an incomplete payload missing the new onboarding questions', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $plan = makeOnboardingPlan();

    $payload = onboardingPayload($plan);
    unset($payload['onboarding_stage'], $payload['chairs_count']);

    $this->actingAs($user)
        ->post(route('onboarding.create-clinic'), $payload)
        ->assertSessionHasErrors(['onboarding_stage', 'chairs_count']);

    expect(Clinic::where('name', 'Clínica Onboarding Teste')->exists())->toBeFalse();
});

test('seeding default chairs for a clinic is idempotent and never duplicates or deletes existing chairs', function () {
    $plan = makeOnboardingPlan();
    $clinic = Clinic::create([
        'name' => 'Clínica Idempotência',
        'slug' => 'clinica-idempotencia-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'trial',
        'plan_id' => $plan->id,
    ]);

    Chair::seedDefaultsForClinic($clinic->id, 3);
    expect(Chair::where('clinic_id', $clinic->id)->count())->toBe(3);

    // Repetir/retomar o fluxo (ex.: onboarding reenviado) não deve duplicar.
    Chair::seedDefaultsForClinic($clinic->id, 3);
    expect(Chair::where('clinic_id', $clinic->id)->count())->toBe(3);

    // Nem sequer tenta criar cadeiras novas quando a clínica já tem
    // qualquer cadeira — mesmo pedindo uma contagem diferente.
    Chair::seedDefaultsForClinic($clinic->id, 6);
    expect(Chair::where('clinic_id', $clinic->id)->count())->toBe(3);
});
