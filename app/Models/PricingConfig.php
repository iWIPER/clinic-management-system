<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingConfig extends Model
{
    use HasFactory, BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'salario_desejado',
        'horas_trabalhadas',
        'custos_fixos',
        'margem_lucro',
    ];

    protected $casts = [
        'salario_desejado' => 'decimal:2',
        'horas_trabalhadas' => 'decimal:2',
        'custos_fixos' => 'decimal:2',
        'margem_lucro' => 'decimal:2',
    ];

    public function horaTecnica(): float
    {
        $horasTrabalhadas = (float) $this->horas_trabalhadas ?: 160;
        $custosBase = (float) $this->salario_desejado + (float) $this->custos_fixos;

        return $horasTrabalhadas > 0 ? $custosBase / $horasTrabalhadas : 0;
    }

    public function horaClinica(): float
    {
        $horaTecnica = $this->horaTecnica();
        $margem = (float) $this->margem_lucro / 100;

        return $margem > 0 ? $horaTecnica / (1 - $margem) : $horaTecnica;
    }
}
