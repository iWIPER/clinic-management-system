<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Convenio extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'nome',
        'ativo',
        'ordem',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function patients()
    {
        return $this->hasMany(Patient::class);
    }

    // patientTreatments() propositalmente ausente: pertence ao módulo de
    // Tratamentos (commitado separadamente) — reintroduzir junto com ele.
}
