<?php

use App\Models\Budget;
use App\Models\Clinic;
use App\Models\ClinicFinancialConnection;
use App\Models\FinancingProposal;
use App\Models\FinancingWebhookEvent;
use App\Models\Patient;
use App\Models\Plan;
use Illuminate\Support\Facades\Crypt;

// Auditoria de segurança — FinancingWebhookProcessor::verifySignature()
// aceitava silenciosamente (return, sem lançar) quando a assinatura ou o
// webhook_secret estavam ausentes, permitindo forjar eventos financeiros
// sem nenhuma autenticação. Corrigido para SEMPRE rejeitar (lançar
// RuntimeException, que o SyncQueue re-lança) quando faltar assinatura,
// faltar secret, ou a assinatura não bater — só uma assinatura HMAC válida
// processa o evento. O bypass de sandbox (ambiente de teste/dev) foi
// preservado sem alteração.

function setupFinancialWebhookContext(string $environment = 'production'): array
{
    $plan = Plan::create([
        'name' => 'Test Plan', 'slug' => 'test-plan-webhook-' . uniqid(), 'is_free' => true,
        'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'max_clinics' => 1,
        'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
    ]);

    $clinic = Clinic::create([
        'name' => 'Clínica Webhook', 'slug' => 'clinica-webhook-' . uniqid(),
        'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
    ]);

    $secret = 'segredo-webhook-teste';

    $connection = ClinicFinancialConnection::create([
        'clinic_id'      => $clinic->id,
        'provider'       => 'banco_bv',
        'environment'    => $environment,
        'status'         => 'active',
        'webhook_secret' => $environment === 'sandbox' ? null : Crypt::encryptString($secret),
    ]);

    $patient = Patient::create(['clinic_id' => $clinic->id, 'nome' => 'Paciente', 'sobrenome' => 'X', 'status' => 'ativo']);
    $budget = Budget::create(['clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'total' => 5000, 'status' => 'rascunho']);

    $proposal = FinancingProposal::create([
        'clinic_id'    => $clinic->id,
        'budget_id'    => $budget->id,
        'patient_id'   => $patient->id,
        'provider'     => 'banco_bv',
        'external_id'  => 'prop_webhook_test',
        'status'       => 'em_analise',
        'amount'       => 5000,
        'installments' => 12,
    ]);

    return compact('clinic', 'connection', 'patient', 'budget', 'proposal', 'secret');
}

function signedFinancialPayload(array $payload, string $secret): string
{
    return hash_hmac('sha256', json_encode($payload), $secret);
}

// A) webhook com assinatura válida → processado
test('a webhook with a valid signature is processed and updates the proposal', function () {
    ['connection' => $connection, 'proposal' => $proposal, 'secret' => $secret] = setupFinancialWebhookContext();

    $payload = ['evento' => 'aprovada', 'proposta_id' => 'prop_webhook_test'];

    $this->postJson(route('financial.webhooks.receive', [
        'provider' => 'banco_bv', 'connectionId' => $connection->id,
    ]), $payload, ['X-Financial-Signature' => signedFinancialPayload($payload, $secret)])
        ->assertOk()
        ->assertJson(['received' => true]);

    expect($proposal->fresh()->status)->toBe('aprovada')
        ->and(FinancingWebhookEvent::where('connection_id', $connection->id)->where('status', 'processed')->exists())->toBeTrue();
});

// B) webhook sem assinatura → rejeitado
test('a webhook with no signature header is rejected and never processed', function () {
    ['connection' => $connection, 'proposal' => $proposal] = setupFinancialWebhookContext();

    $this->postJson(route('financial.webhooks.receive', [
        'provider' => 'banco_bv', 'connectionId' => $connection->id,
    ]), ['evento' => 'aprovada', 'proposta_id' => 'prop_webhook_test'])
        ->assertServerError();

    expect($proposal->fresh()->status)->toBe('em_analise')
        ->and(FinancingWebhookEvent::where('connection_id', $connection->id)->exists())->toBeFalse();
});

// C) webhook com assinatura inválida → rejeitado
test('a webhook with an invalid signature is rejected and never processed', function () {
    ['connection' => $connection, 'proposal' => $proposal] = setupFinancialWebhookContext();

    $this->postJson(route('financial.webhooks.receive', [
        'provider' => 'banco_bv', 'connectionId' => $connection->id,
    ]), ['evento' => 'aprovada', 'proposta_id' => 'prop_webhook_test'], ['X-Financial-Signature' => 'assinatura-forjada'])
        ->assertServerError();

    expect($proposal->fresh()->status)->toBe('em_analise')
        ->and(FinancingWebhookEvent::where('connection_id', $connection->id)->exists())->toBeFalse();
});

// D) webhook sem webhook_secret configurado → rejeitado, mesmo com assinatura enviada
test('a webhook is rejected when the connection has no webhook_secret configured, even with a signature header', function () {
    ['clinic' => $clinic] = setupFinancialWebhookContext();
    $connection = ClinicFinancialConnection::create([
        'clinic_id' => $clinic->id, 'provider' => 'dentalcred', 'environment' => 'production',
        'status' => 'active', 'webhook_secret' => null,
    ]);

    $this->postJson(route('financial.webhooks.receive', [
        'provider' => 'dentalcred', 'connectionId' => $connection->id,
    ]), ['evento' => 'aprovada', 'proposta_id' => 'prop_webhook_test'], ['X-Financial-Signature' => 'qualquer-coisa'])
        ->assertServerError();

    expect(FinancingWebhookEvent::where('connection_id', $connection->id)->exists())->toBeFalse();
});

// E) connectionId inválido → rejeitado
test('an unknown connectionId is rejected with 404 before any signature check', function () {
    $this->postJson(route('financial.webhooks.receive', [
        'provider' => 'banco_bv', 'connectionId' => 999999,
    ]), ['evento' => 'aprovada'])
        ->assertStatus(404);
});

// F) payload malformado (mas assinatura válida) → não derruba nem altera a proposta
test('a malformed payload with a valid signature is safely ignored, not applied to any proposal', function () {
    ['connection' => $connection, 'proposal' => $proposal, 'secret' => $secret] = setupFinancialWebhookContext();

    $payload = ['campo_desconhecido' => 'valor_qualquer'];

    $this->postJson(route('financial.webhooks.receive', [
        'provider' => 'banco_bv', 'connectionId' => $connection->id,
    ]), $payload, ['X-Financial-Signature' => signedFinancialPayload($payload, $secret)])
        ->assertOk();

    expect($proposal->fresh()->status)->toBe('em_analise');

    $event = FinancingWebhookEvent::where('connection_id', $connection->id)->first();
    expect($event)->not->toBeNull()
        ->and($event->status)->toBe('processed')
        ->and($event->event_type)->toBe('desconhecido')
        ->and($event->proposal_id)->toBeNull();
});

// G) webhook legítimo continua funcionando em ambiente sandbox (sem assinatura)
test('a sandbox connection still accepts an unsigned webhook — sandbox bypass preserved', function () {
    ['connection' => $connection, 'proposal' => $proposal] = setupFinancialWebhookContext('sandbox');

    $this->postJson(route('financial.webhooks.receive', [
        'provider' => 'banco_bv', 'connectionId' => $connection->id,
    ]), ['evento' => 'aprovada', 'proposta_id' => 'prop_webhook_test'])
        ->assertOk()
        ->assertJson(['received' => true]);

    expect($proposal->fresh()->status)->toBe('aprovada');
});
