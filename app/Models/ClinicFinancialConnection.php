<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicFinancialConnection extends Model
{
    protected $fillable = [
        'clinic_id',
        'provider',
        'environment',
        'status',
        'client_id',
        'client_secret',
        'access_token',
        'token_expires_at',
        'webhook_secret',
        'webhook_url',
        'last_tested_at',
        'last_sync_at',
        'metadata',
    ];

    protected $hidden = [
        'client_secret',
        'access_token',
        'webhook_secret',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_tested_at'   => 'datetime',
        'last_sync_at'     => 'datetime',
        'metadata'         => 'array',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSandbox(): bool
    {
        return $this->environment === 'sandbox';
    }
}