<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancingWebhookEvent extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'connection_id',
        'proposal_id',
        'provider',
        'event_type',
        'external_id',
        'payload',
        'status',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'processed_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ClinicFinancialConnection::class, 'connection_id');
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(FinancingProposal::class);
    }
}