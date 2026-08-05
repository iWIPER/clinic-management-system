<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trial extends Model
{
    protected $fillable = [
        'clinic_id', 'plan_id', 'started_at', 'ends_at',
        'is_extended', 'extended_by_referral',
    ];

    protected $casts = [
        'started_at'          => 'datetime',
        'ends_at'             => 'datetime',
        'is_extended'         => 'boolean',
        'extended_by_referral'=> 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}