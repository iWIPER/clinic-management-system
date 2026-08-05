<?php

use App\Models\ClinicFinancialConnection;
use App\Services\Financial\FinancialGatewayManager;
use App\Services\Financial\FinancingSimulationService;
use App\DTO\Financial\FinancingSimulationRequest;

function setupFinancialHubContext(): array
{
    $ctx = setupDriveUploadContext();

    ClinicFinancialConnection::create([
        'clinic_id'   => $ctx['clinic']->id,
        'provider'    => 'banco_bv',
        'environment' => 'sandbox',
        'status'      => 'active',
        'metadata'    => ['use_mock' => true],
    ]);

    return $ctx;
}

test('financial gateway manager resolves all registered gateways', function () {
    $manager = app(FinancialGatewayManager::class);

    foreach (array_keys(config('financial.gateways')) as $provider) {
        $gateway = $manager->resolve($provider);
        expect($gateway->provider())->toBe($provider);
    }
});

test('integration test succeeds in sandbox without credentials', function () {
    ['user' => $user, 'clinic' => $clinic] = setupFinancialHubContext();

    $connection = ClinicFinancialConnection::where('clinic_id', $clinic->id)->first();

    $report = app(FinancialGatewayManager::class)
        ->resolve('banco_bv')
        ->testIntegration($connection);

    expect($report->success)->toBeTrue()
        ->and($report->healthScore)->toBeGreaterThan(0);

    $this->actingAs($user)
        ->postJson(route('finance.marketplace.test', 'banco_bv'))
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('financing simulation only runs on user request', function () {
    ['user' => $user, 'clinic' => $clinic, 'patient' => $patient] = setupFinancialHubContext();

    $budget = \App\Models\Budget::create([
        'clinic_id'  => $clinic->id,
        'patient_id' => $patient->id,
        'total'      => 5000,
        'status'     => 'rascunho',
    ]);

    $service = app(FinancingSimulationService::class);
    $result  = $service->simulateForBudget(
        $clinic->id,
        new FinancingSimulationRequest('529.982.247-25', 5000, 12, $budget->id, $patient->id),
        $user,
    );

    expect($result['simulations'])->not->toBeEmpty();

    $this->actingAs($user)
        ->postJson(route('finance.budgets.simulate', $budget), [
            'cpf'          => '529.982.247-25',
            'installments' => 12,
        ])
        ->assertOk()
        ->assertJsonStructure(['simulations', 'failures', 'compared']);
});

test('financial webhook is accepted and queued', function () {
    ['clinic' => $clinic] = setupFinancialHubContext();

    $connection = ClinicFinancialConnection::where('clinic_id', $clinic->id)->first();

    $this->postJson(route('financial.webhooks.receive', [
        'provider'     => 'banco_bv',
        'connectionId' => $connection->id,
    ]), [
        'evento'      => 'aprovada',
        'proposta_id' => 'prop_test_123',
    ])->assertOk()
      ->assertJson(['received' => true]);
});

test('gateway failure does not break finance index', function () {
    ['user' => $user] = setupDriveUploadContext();

    $this->actingAs($user)
        ->get(route('finance.index'))
        ->assertOk();
});