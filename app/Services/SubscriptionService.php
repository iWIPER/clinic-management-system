<?php

namespace App\Services;

use App\Models\AccessLog;
use App\Models\Clinic;
use App\Models\Plan;
use App\Models\ReferralConversion;
use App\Models\ReferralSettings;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\Trial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    public function startTrial(Clinic $clinic, Plan $plan, bool $viaReferral = false): Subscription
    {
        $trialDays = ReferralSettings::current()->trial_days;

        return DB::transaction(function () use ($clinic, $plan, $viaReferral, $trialDays) {
            $subscription = Subscription::create([
                'clinic_id'       => $clinic->id,
                'plan_id'         => $plan->id,
                'status'          => Subscription::STATUS_TRIAL,
                'interval'        => 'monthly',
                'trial_starts_at' => now(),
                'trial_ends_at'   => now()->addDays($trialDays),
            ]);

            Trial::create([
                'clinic_id'           => $clinic->id,
                'plan_id'             => $plan->id,
                'started_at'          => now(),
                'ends_at'             => now()->addDays($trialDays),
                'extended_by_referral'=> $viaReferral,
            ]);

            SubscriptionHistory::create([
                'subscription_id' => $subscription->id,
                'event'           => 'created',
                'plan_id_to'      => $plan->id,
                'notes'           => $viaReferral ? 'Trial iniciado via indicação' : 'Trial iniciado',
                'created_by'      => auth()->id(),
            ]);

            $clinic->update([
                'plan_id' => $plan->id,
                'status'  => 'trial',
            ]);

            AccessLog::record(
                action: 'subscription_trial_started',
                description: "Período de teste iniciado — plano {$plan->name}",
                metadata: ['subscription_id' => $subscription->id, 'plan_id' => $plan->id],
                clinicId: $clinic->id,
            );

            return $subscription;
        });
    }

    public function activatePaid(Clinic $clinic, Subscription $subscription): void
    {
        DB::transaction(function () use ($clinic, $subscription) {
            $subscription->update([
                'status'          => Subscription::STATUS_ACTIVE,
                'starts_at'       => now(),
                'next_billing_at' => now()->addMonth(),
            ]);

            $clinic->update(['status' => 'active']);

            SubscriptionHistory::create([
                'subscription_id' => $subscription->id,
                'event'           => 'activated',
                'plan_id_to'      => $subscription->plan_id,
                'notes'           => 'Assinatura ativada (simulado)',
                'created_by'      => auth()->id(),
            ]);

            $conversion = ReferralConversion::where('referred_clinic_id', $clinic->id)
                ->whereIn('status', [ReferralConversion::STATUS_TESTING, ReferralConversion::STATUS_AWAITING_PAYMENT])
                ->first();

            if ($conversion) {
                $conversion->update([
                    'status'              => ReferralConversion::STATUS_PAYMENT_CONFIRMED,
                    'plan_id'             => $subscription->plan_id,
                    'plan_subscribed_at'  => now(),
                    'payment_confirmed_at'=> now(),
                ]);

                AccessLog::record(
                    action: 'referral_plan_subscribed',
                    description: 'Sua indicação assinou um plano pago.',
                    metadata: ['conversion_id' => $conversion->id],
                    clinicId: $conversion->referral->clinic_id,
                );
            }

            AccessLog::record(
                action: 'subscription_activated',
                description: 'Assinatura de plano pago confirmada',
                metadata: ['subscription_id' => $subscription->id],
                clinicId: $clinic->id,
            );
        });
    }

    public function processEligibleConversions(): int
    {
        $settings = ReferralSettings::current();
        $count    = 0;

        $conversions = ReferralConversion::with(['referral', 'referredClinic.subscription'])
            ->where('status', ReferralConversion::STATUS_PAYMENT_CONFIRMED)
            ->whereNotNull('trial_started_at')
            ->where('trial_started_at', '<=', now()->subDays($settings->trial_days))
            ->get();

        foreach ($conversions as $conversion) {
            $sub = $conversion->referredClinic?->subscription;
            if (! $sub || ! $sub->isActive() || $sub->status === Subscription::STATUS_CANCELLED) {
                continue;
            }

            app(ReferralService::class)->markEligible($conversion);

            AccessLog::record(
                action: 'referral_bonus_eligible',
                description: "Você recebeu R$ {$conversion->reward_amount} por indicação.",
                metadata: ['conversion_id' => $conversion->id],
                clinicId: $conversion->referral->clinic_id,
            );

            $count++;
        }

        Log::info("[SubscriptionService] {$count} conversões marcadas como elegíveis");

        return $count;
    }
}