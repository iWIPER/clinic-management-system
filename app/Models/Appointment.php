<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory, BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'professional_id',
        'treatment_id',
        'chair_id',
        'start',
        'end',
        'status', // scheduled, confirmed, cancelled, no_show, completed
        'notes',
        'reschedule_count',
        'confirmation_requested',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'confirmation_requested' => 'boolean',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function professional()
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function chair()
    {
        return $this->belongsTo(Chair::class);
    }

    public function consultation()
    {
        return $this->hasOne(Consultation::class);
    }

    // Reaproveita o mesmo catálogo de PatientTag usado pelos marcadores do
    // paciente (patient_marker_assignments) — aqui só a associação muda de
    // alvo (Appointment em vez de Patient), ver appointment_tag_assignments.
    public function tags()
    {
        return $this->belongsToMany(PatientTag::class, 'appointment_tag_assignments');
    }

    // Nome do método evita a palavra reservada `return` isolada — embora
    // válida como nome de método em PHP 7+, fica confusa lida ao lado de
    // statements `return` no resto do código.
    public function appointmentReturn()
    {
        return $this->hasOne(AppointmentReturn::class);
    }
}
