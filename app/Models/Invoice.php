<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'clinic_id', 'subscription_id', 'plan_id', 'amount', 'status',
        'due_at', 'paid_at', 'gateway', 'gateway_invoice_id',
    ];

    protected $casts = [
        'amount' => 'float',
        'due_at' => 'datetime',
        'paid_at'=> 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
