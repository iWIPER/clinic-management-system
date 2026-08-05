<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralConversion extends Model
{
    const STATUS_TESTING           = 'testing';
    const STATUS_AWAITING_PAYMENT  = 'awaiting_payment';
    const STATUS_PAYMENT_CONFIRMED = 'payment_confirmed';
    const STATUS_ELIGIBLE          = 'eligible';
    const STATUS_PAID              = 'paid';
    const STATUS_CANCELLED         = 'cancelled';
    const STATUS_EXPIRED           = 'expired';
    const STATUS_REFUNDED          = 'refunded';
    const STATUS_UNDER_REVIEW      = 'under_review';

    const STATUS_LABELS = [
        'testing'           => 'Em Trial',
        'awaiting_payment'  => 'Em carência',
        'payment_confirmed' => 'Pagamento confirmado',
        'eligible'          => 'Liberado',
        'paid'              => 'Pago',
        'cancelled'         => 'Anulado',
        'expired'           => 'Expirado',
        'refunded'          => 'Estornado',
        'under_review'      => 'Em revisão',
    ];

    protected $fillable = [
        'referral_id', 'referred_clinic_id', 'plan_id', 'reward_amount', 'status',
        'trial_started_at', 'plan_subscribed_at', 'payment_confirmed_at',
        'eligible_at', 'paid_at', 'cancelled_at',
    ];

    protected $casts = [
        'reward_amount'         => 'float',
        'trial_started_at'      => 'datetime',
        'plan_subscribed_at'    => 'datetime',
        'payment_confirmed_at'  => 'datetime',
        'eligible_at'           => 'datetime',
        'paid_at'               => 'datetime',
        'cancelled_at'          => 'datetime',
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function referredClinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'referred_clinic_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function daysRemaining(): int
    {
        if (! $this->trial_started_at) return 0;
        $settings = ReferralSettings::current();
        $trialEnd = $this->trial_started_at->copy()->addDays($settings->trial_days);
        return max(0, (int) now()->diffInDays($trialEnd, false));
    }

    public function expectedEligibleAt(): ?\Carbon\Carbon
    {
        if (! $this->trial_started_at) return null;
        $settings = ReferralSettings::current();
        return $this->trial_started_at->copy()->addDays($settings->trial_days);
    }
}
