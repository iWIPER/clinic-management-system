<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralTransaction extends Model
{
    protected $fillable = [
        'wallet_id', 'referral_conversion_id', 'type', 'amount', 'description', 'status',
    ];

    protected $casts = ['amount' => 'float'];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(ReferralWallet::class, 'wallet_id');
    }

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(ReferralConversion::class, 'referral_conversion_id');
    }
}
