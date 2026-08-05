<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancingProposal extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'budget_id',
        'patient_id',
        'simulation_id',
        'submitted_by',
        'provider',
        'external_id',
        'status',
        'amount',
        'installments',
        'signature_url',
        'signed_at',
        'net_amount',
        'fees_amount',
        'expected_settlement_date',
        'settled_at',
        'transaction_id',
        'metadata',
    ];

    protected $casts = [
        'amount'                   => 'decimal:2',
        'net_amount'               => 'decimal:2',
        'fees_amount'              => 'decimal:2',
        'signed_at'                => 'datetime',
        'expected_settlement_date' => 'date',
        'settled_at'               => 'date',
        'metadata'                 => 'array',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function simulation(): BelongsTo
    {
        return $this->belongsTo(FinancingSimulation::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}