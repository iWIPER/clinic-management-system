<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasFactory, BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'nome',
        'marca',
        'lote',
        'validade',
        'custo_unitario',
        'quantidade',
        'quantidade_minima',
        'local',
        'condicao', // bom, vencendo, vencido
    ];

    protected $casts = [
        'validade' => 'date',
        'custo_unitario' => 'decimal:2',
    ];

    public function treatments()
    {
        return $this->belongsToMany(Treatment::class, 'treatment_materials')
                    ->withPivot('quantidade');
    }
}
