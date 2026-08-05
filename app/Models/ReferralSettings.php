<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralSettings extends Model
{
    protected $table = 'referral_settings';

    protected $fillable = [
        'reward_amount', 'referred_discount_amount', 'minimum_withdraw', 'trial_days', 'enabled',
    ];

    protected $casts = [
        'reward_amount'            => 'float',
        'referred_discount_amount' => 'float',
        'minimum_withdraw'         => 'float',
        'trial_days'               => 'integer',
        'enabled'                  => 'boolean',
    ];

    public static function current(): static
    {
        return static::firstOrCreate([], [
            'reward_amount'            => 50.00,
            'referred_discount_amount' => 30.00,
            'minimum_withdraw'         => 100.00,
            'trial_days'               => 7,
            'enabled'                  => true,
        ]);
    }
}
