<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Treatment extends Model
{
    use HasFactory, BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'nome',
        'especialidade',
        'duracao_padrao', // minutos
        'preco_base',
        'descricao',
        'ativo',
    ];

    protected $casts = [
        'preco_base' => 'decimal:2',
        'ativo' => 'boolean',
    ];

    public function materials()
    {
        return $this->belongsToMany(InventoryItem::class, 'treatment_materials')
                    ->withPivot('quantidade')
                    ->withTimestamps();
    }
}
