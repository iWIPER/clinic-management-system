<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    const STATUS_TRIAL     = 'trial';
    const STATUS_ACTIVE    = 'active';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED   = 'expired';
    const STATUS_PAUSED    = 'paused';

    protected $fillable = [
        'clinic_id', 'plan_id', 'status', 'interval',
        'trial_starts_at', 'trial_ends_at', 'starts_at', 'ends_at',
        'cancelled_at', 'next_billing_at', 'gateway', 'gateway_subscription_id',
    ];

    protected $casts = [
        'trial_starts_at'  => 'datetime',
        'trial_ends_at'    => 'datetime',
        'starts_at'        => 'datetime',
        'ends_at'          => 'datetime',
        'cancelled_at'     => 'datetime',
        'next_billing_at'  => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(SubscriptionHistory::class)->latest('created_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_TRIAL, self::STATUS_ACTIVE]);
    }

    public function trialDaysRemaining(): int
    {
        if (! $this->trial_ends_at) return 0;
        return max(0, (int) now()->diffInDays($this->trial_ends_at, false));
    }
}
