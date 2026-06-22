<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory, BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'tipo',           // receita, despesa
        'valor',
        'categoria',
        'descricao',
        'origem_type',    // App\Models\ProcedureExecution, manual, etc
        'origem_id',
        'caixa',          // principal, secundario...
        'status',         // pendente, pago, cancelado
        'vencimento',
        'pago_em',
        'patient_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'vencimento' => 'date',
        'pago_em' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
