<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Plan;
use App\Models\ReferralConversion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Cashier\Cashier;

class CheckoutController extends Controller
{
    /**
     * Whether this clinic arrived via a referral link and hasn't paid yet —
     * the only case where the invited-customer discount applies.
     */
    private function eligibleConversion(Clinic $clinic): ?ReferralConversion
    {
        return ReferralConversion::where('referred_clinic_id', $clinic->id)
            ->whereIn('status', [
                ReferralConversion::STATUS_TESTING,
                ReferralConversion::STATUS_AWAITING_PAYMENT,
            ])
            ->first();
    }

    private function priceIdForPlan(Plan $plan, string $interval): ?string
    {
        return $interval === 'yearly' ? $plan->stripe_price_id_yearly : $plan->stripe_price_id_monthly;
    }

    // ── Resumo do checkout — Plano, valor, desconto de convite, total ─────
    public function show(Request $request, Plan $plan): \Inertia\Response
    {
        $clinic     = Clinic::findOrFail(session('current_clinic_id'));
        $interval   = $request->get('interval', 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        $conversion = $this->eligibleConversion($clinic);

        $priceCents = $interval === 'yearly' ? $plan->price_yearly_cents : $plan->price_monthly_cents;
        $discount   = $conversion ? \App\Models\ReferralSettings::current()->referred_discount_amount : 0;
        $totalCents = max(0, $priceCents - (int) round($discount * 100));

        return Inertia::render('Checkout/Show', [
            'plan' => [
                'name'     => $plan->name,
                'slug'     => $plan->slug,
                'interval' => $interval,
            ],
            'price_amount'    => $priceCents / 100,
            'discount_amount' => $discount,
            'total_amount'    => $totalCents / 100,
            'has_discount'    => (bool) $conversion,
        ]);
    }

    // ── Cria a sessão de Checkout no Stripe e redireciona ──────────────────
    public function store(Request $request, Plan $plan): \Illuminate\Http\RedirectResponse
    {
        $clinic     = Clinic::findOrFail(session('current_clinic_id'));
        $interval   = $request->get('interval', 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        $priceId    = $this->priceIdForPlan($plan, $interval);
        $conversion = $this->eligibleConversion($clinic);

        abort_unless($priceId, 422, 'Este plano ainda não está configurado para cobrança no Stripe.');

        $checkoutOptions = [
            'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => route('checkout.show', $plan->slug),
        ];

        if ($conversion) {
            $settings = \App\Models\ReferralSettings::current();

            if ($settings->referred_discount_amount > 0) {
                $coupon = Cashier::stripe()->coupons->create([
                    'amount_off' => (int) round($settings->referred_discount_amount * 100),
                    'currency'   => config('cashier.currency', 'brl'),
                    'duration'   => 'once',
                    'name'       => 'Desconto de indicação',
                ]);

                $checkoutOptions['discounts'] = [['coupon' => $coupon->id]];
            }
        }

        $checkoutSession = $clinic
            ->newSubscription('default', $priceId)
            ->checkout($checkoutOptions);

        return redirect($checkoutSession->url);
    }

    // ── Retorno do Stripe após checkout bem-sucedido ───────────────────────
    // A ativação real acontece via webhook (StripeWebhookController) — esta
    // rota só entrega uma mensagem imediata ao usuário.
    public function success(): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('dashboard', ['subscribed' => 1])
            ->with('success', 'Assinatura confirmada! A ativação é processada automaticamente em instantes.');
    }
}
