<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Consultation extends Model
{
    use HasFactory, BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'professional_id',
        'appointment_id',
        'status', // aguardando, em_atendimento, finalizado, cancelado
        'check_in_at',
        'started_at',
        'finished_at',
        'notes',
    ];

    protected $casts = [
        'check_in_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function patient() { return $this->belongsTo(Patient::class); }
    public function professional() { return $this->belongsTo(User::class, 'professional_id'); }
    public function appointment() { return $this->belongsTo(Appointment::class); }

    public function procedureExecutions()
    {
        return $this->hasMany(ProcedureExecution::class);
    }

    public function clinicalRecord()
    {
        return $this->hasOne(ClinicalRecord::class);
    }
}
