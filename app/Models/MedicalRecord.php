<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'consultation_id',
        'clinic_id',
        'subjective',  // S - subjetivo (queixa, anamnese)
        'objective',   // O - objetivo (exame físico)
        'assessment',  // A - avaliação / diagnóstico
        'plan',        // P - plano / conduta
        'notes',
    ];

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }
}
