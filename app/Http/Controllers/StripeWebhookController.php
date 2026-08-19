<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\StripeWebhookEvent;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extends Cashier's own webhook controller to additionally plug Stripe's
 * checkout confirmation into this app's own domain-level
 * Subscription/ReferralConversion bookkeeping (App\Services\SubscriptionService),
 * which Cashier has no knowledge of. Every event is recorded in
 * stripe_webhook_events first, keyed by Stripe's own event id, so a retried
 * delivery never double-activates a subscription or double-releases a bônus.
 *
 * Constructor overridden (não herdado do Cashier): o WebhookController do
 * Cashier só registra VerifyWebhookSignature quando cashier.webhook.secret
 * está configurado — com o secret vazio (hoje o caso em produção, ver
 * BillingCheckoutStripeTest), ele simplesmente processa qualquer POST sem
 * verificar assinatura nenhuma. Fail-closed aqui: sem secret configurado,
 * o endpoint inteiro recusa a requisição (503) em qualquer ambiente, antes
 * de handleWebhook() rodar — nunca aceita payload não verificado. Não
 * reimplementa verificação de assinatura própria; usa o mesmo
 * VerifyWebhookSignature (Stripe\WebhookSignature::verifyHeader) do
 * Cashier quando o secret existe.
 */
class StripeWebhookController extends CashierWebhookController
{
    public function __construct()
    {
        if (! config('cashier.webhook.secret')) {
            abort(503, 'Stripe webhook secret is not configured; refusing to process webhook requests.');
        }

        $this->middleware(VerifyWebhookSignature::class);
    }

    protected function handleCheckoutSessionCompleted(array $payload): Response
    {
        $eventId = $payload['id'] ?? null;

        if (! $eventId || StripeWebhookEvent::where('stripe_event_id', $eventId)->exists()) {
            return new Response('Webhook Handled', 200);
        }

        $event = StripeWebhookEvent::create([
            'stripe_event_id' => $eventId,
            'type'            => $payload['type'] ?? 'checkout.session.completed',
            'status'          => 'received',
            'payload'         => $payload,
        ]);

        try {
            $session       = $payload['data']['object'] ?? [];
            $stripeCustomerId = $session['customer'] ?? null;

            $clinic = $stripeCustomerId
                ? Clinic::where('stripe_id', $stripeCustomerId)->first()
                : null;

            if (! $clinic) {
                $event->update(['status' => 'failed', 'error' => 'Clinic not found for Stripe customer.']);
                Log::warning('[StripeWebhook] Clínica não encontrada para o customer do Stripe', [
                    'stripe_customer_id' => $stripeCustomerId,
                    'event_id'           => $eventId,
                ]);

                return new Response('Webhook Handled', 200);
            }

            $subscription = Subscription::where('clinic_id', $clinic->id)->latest()->first();

            if ($subscription) {
                app(SubscriptionService::class)->activatePaid($clinic, $subscription);
            }

            $event->update(['status' => 'processed']);
        } catch (\Throwable $e) {
            $event->update(['status' => 'failed', 'error' => $e->getMessage()]);
            Log::error('[StripeWebhook] Falha ao processar checkout.session.completed', [
                'event_id' => $eventId,
                'error'    => $e->getMessage(),
            ]);
        }

        return new Response('Webhook Handled', 200);
    }
}
