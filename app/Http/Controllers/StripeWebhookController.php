<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\StripeWebhookEvent;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extends Cashier's own webhook controller (signature verification already
 * wired via its constructor when cashier.webhook.secret is set) to additionally
 * plug Stripe's checkout confirmation into this app's own domain-level
 * Subscription/ReferralConversion bookkeeping (App\Services\SubscriptionService),
 * which Cashier has no knowledge of. Every event is recorded in
 * stripe_webhook_events first, keyed by Stripe's own event id, so a retried
 * delivery never double-activates a subscription or double-releases a bônus.
 */
class StripeWebhookController extends CashierWebhookController
{
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
