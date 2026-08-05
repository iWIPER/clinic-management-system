<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralClick extends Model
{
    public $timestamps = false;

    protected $fillable = ['referral_id', 'ip_address', 'user_agent', 'referred_clinic_id'];

    protected $casts = ['created_at' => 'datetime'];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function referredClinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'referred_clinic_id');
    }
}
