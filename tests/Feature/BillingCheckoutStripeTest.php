<?php

use App\Models\Clinic;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\ReferralConversion;
use App\Models\Subscription;
use App\Models\StripeWebhookEvent;
use App\Models\User;

function setupBillingContext(string $suffix = ''): array
{
    $plan = Plan::create([
        'name' => 'Plano Pro',
        'slug' => 'plano-pro' . $suffix . '-' . uniqid(),
        'is_free' => false,
        'price_monthly_cents' => 19900,
        'price_yearly_cents' => 199000,
        'max_clinics' => 1,
        'max_patients' => 500,
        'max_users' => 10,
        'storage_gb' => 10,
        'features' => [],
        'stripe_price_id_monthly' => 'price_monthly_test',
        'stripe_price_id_yearly' => 'price_yearly_test',
    ]);

    $clinic = Clinic::create([
        'name' => 'Clínica Cobrança' . $suffix,
        'slug' => 'clinica-cobranca' . $suffix . '-' . uniqid(),
        'type' => 'odontologia',
        'status' => 'trial',
        'plan_id' => $plan->id,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $clinic->users()->attach($user->id, ['role' => 'owner']);
    session(['current_clinic_id' => $clinic->id]);

    return compact('plan', 'clinic', 'user');
}

// ---------------------------------------------------------------------------
// CheckoutController — testado sem tocar a rede real do Stripe: só o que não
// depende de uma chamada HTTP de verdade à API do Stripe (guard clauses,
// cálculo de preço/desconto, renderização da página, redirect de sucesso).
// A criação da sessão de Checkout em si (Cashier::stripe()->checkout(...))
// não é exercitada aqui — faria uma chamada de rede real sem uma chave de
// teste do Stripe configurada, o que violaria "não alterar lógica de
// produção só para facilitar testes".
// ---------------------------------------------------------------------------

describe('CheckoutController::show', function () {
    test('unauthenticated access is redirected to login', function () {
        $plan = Plan::create([
            'name' => 'Plano Público', 'slug' => 'plano-publico-' . uniqid(), 'is_free' => false,
            'price_monthly_cents' => 9900, 'price_yearly_cents' => 99000,
            'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
        ]);

        $this->get(route('checkout.show', $plan->slug))->assertRedirect(route('login'));
    });

    test('renders plan pricing with no discount when there is no eligible referral conversion', function () {
        ['plan' => $plan, 'user' => $user] = setupBillingContext();

        $this->actingAs($user)
            ->get(route('checkout.show', $plan->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Checkout/Show')
                ->where('price_amount', 199)
                ->where('has_discount', false)
                ->where('discount_amount', 0)
            );
    });

    test('applies the referral discount when clinic has an eligible conversion awaiting payment', function () {
        ['plan' => $plan, 'clinic' => $clinic, 'user' => $user] = setupBillingContext();

        \App\Models\ReferralSettings::current()->update(['referred_discount_amount' => 20]);

        $referrerClinic = Clinic::create([
            'name' => 'Clínica Indicadora', 'slug' => 'clinica-indicadora-' . uniqid(),
            'type' => 'odontologia', 'status' => 'active', 'plan_id' => $plan->id,
        ]);
        $referral = Referral::create([
            'clinic_id' => $referrerClinic->id,
            'code' => 'REF-' . uniqid(),
            'is_active' => true,
        ]);

        ReferralConversion::create([
            'referral_id' => $referral->id,
            'referred_clinic_id' => $clinic->id,
            'status' => ReferralConversion::STATUS_AWAITING_PAYMENT,
        ]);

        $this->actingAs($user)
            ->get(route('checkout.show', $plan->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Checkout/Show')
                ->where('has_discount', true)
                ->where('discount_amount', 20)
                ->where('total_amount', 179)
            );
    });

    test('yearly interval uses the yearly price', function () {
        ['plan' => $plan, 'user' => $user] = setupBillingContext();

        $this->actingAs($user)
            ->get(route('checkout.show', $plan->slug) . '?interval=yearly')
            ->assertInertia(fn ($page) => $page->where('price_amount', 1990));
    });
});

describe('CheckoutController::store', function () {
    test('unauthenticated access is redirected to login', function () {
        ['plan' => $plan] = setupBillingContext();

        $this->post(route('checkout.store', $plan->slug))->assertRedirect(route('login'));
    });

    test('a plan without a configured Stripe price id is rejected before any Stripe call', function () {
        ['user' => $user] = setupBillingContext();

        $planWithoutPrice = Plan::create([
            'name' => 'Plano Sem Preço', 'slug' => 'plano-sem-preco-' . uniqid(), 'is_free' => false,
            'price_monthly_cents' => 5000, 'price_yearly_cents' => 50000,
            'max_clinics' => 1, 'max_patients' => 100, 'max_users' => 5, 'storage_gb' => 1, 'features' => [],
            'stripe_price_id_monthly' => null,
        ]);

        $this->actingAs($user)
            ->post(route('checkout.store', $planWithoutPrice->slug))
            ->assertStatus(422);
    });
});

test('checkout.success redirects to the dashboard with a pending-activation message, without calling Stripe', function () {
    ['user' => $user] = setupBillingContext();

    $this->actingAs($user)
        ->get(route('checkout.success'))
        ->assertRedirect(route('dashboard', ['subscribed' => 1]))
        ->assertSessionHas('success');
});

// ---------------------------------------------------------------------------
// StripeWebhookController
// ---------------------------------------------------------------------------

function stripeSignatureHeader(string $payload, string $secret, ?int $timestamp = null): string
{
    $timestamp ??= time();
    $signedPayload = $timestamp . '.' . $payload;
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    return "t={$timestamp},v1={$signature}";
}

// Todo POST pra /stripe/webhook precisa vir assinado — StripeWebhookController
// agora recusa (503) qualquer requisição quando cashier.webhook.secret não
// está configurado (ver seu construtor), então mesmo os testes de fluxo de
// negócio (nada a ver com verificação de assinatura em si) precisam
// configurar um secret de teste e assinar o payload, senão nunca passariam
// da fase "secret ausente". `whsec_test_secret` é só um valor de teste
// (config() em runtime, nunca toca .env/.env.example).
function postSignedWebhook(array $payload, string $secret = 'whsec_test_secret')
{
    config(['cashier.webhook.secret' => $secret]);
    $json = json_encode($payload);

    return test()->call('POST', route('cashier.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_Stripe-Signature' => stripeSignatureHeader($json, $secret),
    ], $json);
}

test('checkout.session.completed activates the subscription and clinic exactly once, even if the event is replayed', function () {
    ['clinic' => $clinic, 'plan' => $plan] = setupBillingContext();
    $clinic->forceFill(['stripe_id' => 'cus_test_123'])->save();

    $subscription = Subscription::create([
        'clinic_id' => $clinic->id,
        'plan_id' => $plan->id,
        'status' => Subscription::STATUS_TRIAL,
        'interval' => 'monthly',
    ]);

    $payload = [
        'id' => 'evt_test_1',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['customer' => 'cus_test_123']],
    ];

    postSignedWebhook($payload)->assertOk();

    $subscription->refresh();
    $clinic->refresh();
    expect($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($clinic->status)->toBe('active')
        ->and(StripeWebhookEvent::where('stripe_event_id', 'evt_test_1')->where('status', 'processed')->exists())->toBeTrue();

    // Replay do mesmo evento (Stripe reenvia em caso de timeout/retry) — não
    // deve processar de novo nem duplicar SubscriptionHistory.
    $historyCountAfterFirst = \App\Models\SubscriptionHistory::where('subscription_id', $subscription->id)->count();

    postSignedWebhook($payload)->assertOk();

    expect(StripeWebhookEvent::where('stripe_event_id', 'evt_test_1')->count())->toBe(1)
        ->and(\App\Models\SubscriptionHistory::where('subscription_id', $subscription->id)->count())->toBe($historyCountAfterFirst);
});

test('checkout.session.completed for an unknown Stripe customer is recorded as failed but still responds 200', function () {
    $payload = [
        'id' => 'evt_test_unknown_customer',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['customer' => 'cus_does_not_exist']],
    ];

    postSignedWebhook($payload)->assertOk();

    $event = StripeWebhookEvent::where('stripe_event_id', 'evt_test_unknown_customer')->first();
    expect($event)->not->toBeNull()
        ->and($event->status)->toBe('failed');
});

test('an event type with no handler is acknowledged without error (Cashier missingMethod fallback)', function () {
    $payload = [
        'id' => 'evt_test_unhandled',
        'type' => 'payment_intent.created',
        'data' => ['object' => []],
    ];

    postSignedWebhook($payload)->assertOk();

    expect(StripeWebhookEvent::where('stripe_event_id', 'evt_test_unhandled')->exists())->toBeFalse();
});

// Fail-closed: sem secret configurado, o endpoint recusa TUDO antes de
// handleWebhook() rodar — nunca processa um payload sem verificar
// assinatura (ver StripeWebhookController::__construct()). Nenhum evento é
// gravado nem processado; a requisição nem chega perto do payload.
test('webhook requests are rejected outright when no signing secret is configured (fail-closed)', function () {
    config(['cashier.webhook.secret' => null]);

    $payload = [
        'id' => 'evt_test_no_secret_configured',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['customer' => 'cus_test_123']],
    ];

    $this->postJson(route('cashier.webhook'), $payload)
        ->assertStatus(503);

    expect(StripeWebhookEvent::where('stripe_event_id', 'evt_test_no_secret_configured')->exists())->toBeFalse();
});

// Os testes abaixo provam que o mecanismo de verificação de assinatura do
// Cashier (Stripe\WebhookSignature::verifyHeader, via VerifyWebhookSignature)
// funciona corretamente quando o secret está configurado — o mesmo secret
// de teste usado por postSignedWebhook() acima, nunca um valor real.
test('with a webhook secret configured, a correctly signed request is accepted', function () {
    config(['cashier.webhook.secret' => 'whsec_test_secret']);

    $payload = json_encode([
        'id' => 'evt_test_signed_ok',
        'type' => 'payment_intent.created',
        'data' => ['object' => []],
    ]);

    $this->call('POST', route('cashier.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_Stripe-Signature' => stripeSignatureHeader($payload, 'whsec_test_secret'),
    ], $payload)->assertOk();
});

test('with a webhook secret configured, a request with an invalid signature is rejected with 403', function () {
    config(['cashier.webhook.secret' => 'whsec_test_secret']);

    $payload = json_encode([
        'id' => 'evt_test_signed_bad',
        'type' => 'payment_intent.created',
        'data' => ['object' => []],
    ]);

    $this->call('POST', route('cashier.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_Stripe-Signature' => stripeSignatureHeader($payload, 'wrong_secret'),
    ], $payload)->assertStatus(403);

    expect(StripeWebhookEvent::where('stripe_event_id', 'evt_test_signed_bad')->exists())->toBeFalse();
});

test('with a webhook secret configured, a request with no signature header at all is rejected with 403', function () {
    config(['cashier.webhook.secret' => 'whsec_test_secret']);

    $this->postJson(route('cashier.webhook'), [
        'id' => 'evt_test_no_sig',
        'type' => 'payment_intent.created',
        'data' => ['object' => []],
    ])->assertStatus(403);
});
