<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicalEvolution extends Model
{
    use HasFactory, BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'professional_id',
        'consultation_id',
        'patient_treatment_id',
        'content',
        'signature_required',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at'        => 'datetime',
        'signature_required' => 'boolean',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function professional()
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }

    public function patientTreatment()
    {
        return $this->belongsTo(PatientTreatment::class);
    }

    public function photos()
    {
        return $this->hasMany(PatientPhoto::class);
    }

    public function signature()
    {
        return $this->hasOne(ClinicalEvolutionSignature::class, 'evolution_id');
    }
}