<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientAnamnesis extends Model
{
    use HasFactory, BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'queixa_principal',
        'historico_medico',
        'alergias',
        'medicamentos_em_uso',
        'doencas_sistemicas',
        'historico_familiar',
        'gestante',
        'hipertensao',
        'diabetes',
        'cardiopatia',
        'hemorragia',
        'fumo',
        'alcool',
        'habitos_outros',
        'cirurgias_previas',
        'observacoes',
        'updated_by_id',
    ];

    protected $casts = [
        'gestante' => 'boolean',
        'hipertensao' => 'boolean',
        'diabetes' => 'boolean',
        'cardiopatia' => 'boolean',
        'hemorragia' => 'boolean',
        'fumo' => 'boolean',
        'alcool' => 'boolean',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}