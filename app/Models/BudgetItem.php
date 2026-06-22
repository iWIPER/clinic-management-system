<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'treatment_id',
        'descricao',
        'quantidade',
        'preco_unitario',
        'total',
    ];

    protected $casts = [
        'quantidade' => 'integer',
        'preco_unitario' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }
}
