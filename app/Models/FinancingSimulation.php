<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancingSimulation extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'budget_id',
        'patient_id',
        'requested_by',
        'provider',
        'amount',
        'installments',
        'cpf_hash',
        'external_id',
        'installment_value',
        'total_amount',
        'interest_rate',
        'cet',
        'fees',
        'raw_response',
    ];

    protected $casts = [
        'amount'            => 'decimal:2',
        'installment_value' => 'decimal:2',
        'total_amount'      => 'decimal:2',
        'interest_rate'     => 'decimal:4',
        'cet'               => 'decimal:4',
        'fees'              => 'decimal:2',
        'raw_response'      => 'array',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}