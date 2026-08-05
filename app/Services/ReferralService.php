<?php

namespace App\Services;

use App\Models\AccessLog;
use App\Models\Clinic;
use App\Models\Referral;
use App\Models\ReferralClick;
use App\Models\ReferralConversion;
use App\Models\ReferralSettings;
use App\Models\ReferralTransaction;
use App\Models\ReferralWallet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReferralService
{
    /**
     * @param  Clinic|User  $owner  A Clinic (regular clinic-referrer) or a
     *                              standalone affiliate User — never both.
     */
    private function ownerKey(Clinic|User $owner): array
    {
        return $owner instanceof Clinic
            ? ['clinic_id' => $owner->id]
            : ['affiliate_user_id' => $owner->id];
    }

    // ── Obter ou criar o referral de uma clínica/afiliado ─────────────────
    public function getOrCreate(Clinic|User $owner): Referral
    {
        return Referral::firstOrCreate(
            $this->ownerKey($owner),
            ['code' => Referral::generateCode(), 'is_active' => true]
        );
    }

    // ── Obter ou criar carteira ───────────────────────────────────────────
    public function getOrCreateWallet(Clinic|User $owner): ReferralWallet
    {
        return ReferralWallet::firstOrCreate(
            $this->ownerKey($owner),
            ['balance' => 0, 'pending_balance' => 0, 'total_earned' => 0, 'total_withdrawn' => 0]
        );
    }

    // ── Rastrear clique ───────────────────────────────────────────────────
    public function trackClick(Referral $referral, Request $request): void
    {
        try {
            ReferralClick::create([
                'referral_id' => $referral->id,
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
            ]);

            $referral->increment('clicks_count');

            AccessLog::record(
                action: 'referral_click',
                description: "Clique no link de indicação {$referral->code}",
                metadata: ['referral_id' => $referral->id, 'code' => $referral->code],
                clinicId: $referral->loggableClinicId(),
            );

            Log::info('[ReferralService] Clique registrado', [
                'referral_id' => $referral->id,
                'code'        => $referral->code,
                'ip'          => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[ReferralService] Erro ao registrar clique', ['error' => $e->getMessage()]);
        }
    }

    // ── Registrar conversão (cadastro via link) ───────────────────────────
    public function registerConversion(Referral $referral, Clinic $referredClinic, ?Request $request = null): ReferralConversion
    {
        $settings = ReferralSettings::current();

        return DB::transaction(function () use ($referral, $referredClinic, $settings, $request) {
            // Associar click ao clinic registrado
            if ($request) {
                ReferralClick::where('referral_id', $referral->id)
                    ->where('ip_address', $request->ip())
                    ->whereNull('referred_clinic_id')
                    ->latest('created_at')
                    ->limit(1)
                    ->update(['referred_clinic_id' => $referredClinic->id]);
            }

            $conversion = ReferralConversion::create([
                'referral_id'       => $referral->id,
                'referred_clinic_id'=> $referredClinic->id,
                'reward_amount'     => $settings->reward_amount,
                'status'            => ReferralConversion::STATUS_TESTING,
                'trial_started_at'  => now(),
            ]);

            $referral->increment('conversions_count');

            // Criar transação pendente na carteira
            $wallet = $this->getOrCreateWallet($referral->owner());
            ReferralTransaction::create([
                'wallet_id'              => $wallet->id,
                'referral_conversion_id' => $conversion->id,
                'type'                   => 'pending',
                'amount'                 => $settings->reward_amount,
                'description'            => "Indicação de {$referredClinic->name} — aguardando elegibilidade",
                'status'                 => 'pending',
            ]);

            $wallet->increment('pending_balance', $settings->reward_amount);

            Log::info('[ReferralService] Conversão registrada', [
                'referral_id'        => $referral->id,
                'referred_clinic_id' => $referredClinic->id,
                'reward_amount'      => $settings->reward_amount,
            ]);

            AccessLog::record(
                action: 'referral_conversion_registered',
                description: "{$referredClinic->name} iniciou período de teste via indicação de {$referral->ownerDisplayName()}",
                metadata: ['referral_id' => $referral->id, 'conversion_id' => $conversion->id],
                clinicId: $referral->loggableClinicId(),
            );

            return $conversion;
        });
    }

    // ── Marcar conversão como elegível (trial encerrado + pagamento) ──────
    public function markEligible(ReferralConversion $conversion): void
    {
        DB::transaction(function () use ($conversion) {
            $conversion->update([
                'status'      => ReferralConversion::STATUS_ELIGIBLE,
                'eligible_at' => now(),
            ]);

            // Mover de pending para balance
            $wallet = $this->getOrCreateWallet($conversion->referral->owner());

            $wallet->decrement('pending_balance', $conversion->reward_amount);
            $wallet->increment('balance', $conversion->reward_amount);
            $wallet->increment('total_earned', $conversion->reward_amount);

            ReferralTransaction::where('referral_conversion_id', $conversion->id)
                ->update(['type' => 'released', 'status' => 'confirmed',
                    'description' => "Bônus liberado — indicação elegível: {$conversion->referredClinic->name}"]);

            Log::info('[ReferralService] Bônus liberado', ['conversion_id' => $conversion->id]);

            AccessLog::record(
                action: 'referral_bonus_released',
                description: "Bônus de R$ {$conversion->reward_amount} liberado por indicação de {$conversion->referredClinic->name}",
                metadata: ['conversion_id' => $conversion->id],
                clinicId: $conversion->referral->loggableClinicId(),
            );
        });
    }

    // ── Marcar conversão como paga (admin aprova saque) ──────────────────
    public function markPaid(ReferralConversion $conversion, int $processedBy): void
    {
        DB::transaction(function () use ($conversion, $processedBy) {
            $conversion->update([
                'status'  => ReferralConversion::STATUS_PAID,
                'paid_at' => now(),
            ]);

            $wallet = $this->getOrCreateWallet($conversion->referral->owner());
            $wallet->decrement('balance', $conversion->reward_amount);
            $wallet->increment('total_withdrawn', $conversion->reward_amount);
            $wallet->update(['last_payment_at' => now()]);

            ReferralTransaction::create([
                'wallet_id'              => $wallet->id,
                'referral_conversion_id' => $conversion->id,
                'type'                   => 'debit',
                'amount'                 => $conversion->reward_amount,
                'description'            => "Pagamento realizado via PIX",
                'status'                 => 'confirmed',
            ]);

            Log::info('[ReferralService] Pagamento confirmado', [
                'conversion_id' => $conversion->id,
                'processed_by'  => $processedBy,
            ]);
        });
    }

    // ── Cancelar conversão ────────────────────────────────────────────────
    public function cancel(ReferralConversion $conversion, string $reason = ''): void
    {
        DB::transaction(function () use ($conversion, $reason) {
            $oldStatus = $conversion->status;

            // Reverter pending_balance se ainda estava pendente
            if (in_array($conversion->status, [
                ReferralConversion::STATUS_TESTING,
                ReferralConversion::STATUS_AWAITING_PAYMENT,
            ])) {
                $wallet = $this->getOrCreateWallet($conversion->referral->owner());
                if ($wallet->pending_balance >= $conversion->reward_amount) {
                    $wallet->decrement('pending_balance', $conversion->reward_amount);
                }
            }

            $conversion->update([
                'status'       => ReferralConversion::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            ReferralTransaction::where('referral_conversion_id', $conversion->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            Log::info('[ReferralService] Conversão cancelada', [
                'conversion_id' => $conversion->id,
                'old_status'    => $oldStatus,
                'reason'        => $reason,
            ]);
        });
    }

    // ── Marcar como estornado (pagamento do convidado foi revertido) ──────
    public function markRefunded(ReferralConversion $conversion, string $reason = ''): void
    {
        DB::transaction(function () use ($conversion, $reason) {
            $wallet = $this->getOrCreateWallet($conversion->referral->owner());

            // Retira o bônus de onde quer que ele esteja (liberado ou ainda pendente)
            if ($conversion->status === ReferralConversion::STATUS_ELIGIBLE) {
                $wallet->decrement('balance', $conversion->reward_amount);
            } elseif ($wallet->pending_balance >= $conversion->reward_amount) {
                $wallet->decrement('pending_balance', $conversion->reward_amount);
            }

            $conversion->update(['status' => ReferralConversion::STATUS_REFUNDED]);

            ReferralTransaction::create([
                'wallet_id'              => $wallet->id,
                'referral_conversion_id' => $conversion->id,
                'type'                   => 'debit',
                'amount'                 => $conversion->reward_amount,
                'description'            => 'Bônus estornado — ' . ($reason ?: 'pagamento do indicado revertido'),
                'status'                 => 'confirmed',
            ]);

            Log::info('[ReferralService] Conversão estornada', [
                'conversion_id' => $conversion->id,
                'reason'        => $reason,
            ]);
        });
    }

    // ── Marcar como em revisão (suspeita de fraude/abuso) ──────────────────
    public function markUnderReview(ReferralConversion $conversion, string $reason = ''): void
    {
        $conversion->update(['status' => ReferralConversion::STATUS_UNDER_REVIEW]);

        Log::info('[ReferralService] Conversão colocada em revisão', [
            'conversion_id' => $conversion->id,
            'reason'        => $reason,
        ]);
    }

    // ── Estatísticas do dashboard ─────────────────────────────────────────
    public function stats(Clinic|User $owner): array
    {
        $key      = $this->ownerKey($owner);
        $referral = Referral::where($key)->first();
        $wallet   = ReferralWallet::where($key)->first();

        return [
            'balance'          => $wallet?->balance ?? 0,
            'pending_balance'  => $wallet?->pending_balance ?? 0,
            'total_earned'     => $wallet?->total_earned ?? 0,
            'total_withdrawn'  => $wallet?->total_withdrawn ?? 0,
            'negative_balance' => $wallet && $wallet->balance < 0 ? abs($wallet->balance) : 0,
            'referrals'        => $referral?->conversions_count ?? 0,
            'conversions'      => $referral ? $referral->conversions()->whereIn('status', [
                ReferralConversion::STATUS_ELIGIBLE,
                ReferralConversion::STATUS_PAID,
                ReferralConversion::STATUS_PAYMENT_CONFIRMED,
            ])->count() : 0,
            'trials_active'    => $referral
                ? $referral->conversions()->where('status', ReferralConversion::STATUS_TESTING)->count()
                : 0,
            'subscriptions'    => $referral ? $referral->conversions()->whereIn('status', [
                ReferralConversion::STATUS_PAYMENT_CONFIRMED,
                ReferralConversion::STATUS_ELIGIBLE,
                ReferralConversion::STATUS_PAID,
            ])->count() : 0,
            'conversion_rate'  => $referral?->conversionRate() ?? 0.0,
            'clicks'           => $referral?->clicks_count ?? 0,
        ];
    }
}
