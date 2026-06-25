<?php

namespace App\Models;

use App\Enums\ClinicalRecordStatus;
use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicalRecord extends Model
{
    use HasFactory, BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'professional_id',
        'appointment_id',
        'consultation_id',
        'procedure_name',
        'procedure_category',
        'status',
        'started_at',
        'finished_at',
        'duration_minutes',
        'price',
        'notes',
        'pdf_path',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'price' => 'decimal:2',
        'status' => ClinicalRecordStatus::class,
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function professional()
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }
}