<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralPayment extends Model
{
    protected $fillable = [
        'wallet_id', 'amount', 'pix_type', 'pix_key', 'status', 'notes',
        'requested_at', 'processed_at', 'processed_by',
    ];

    protected $casts = [
        'amount'       => 'float',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(ReferralWallet::class, 'wallet_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
