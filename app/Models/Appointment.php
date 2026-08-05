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
        'start',
        'end',
        'status', // scheduled, confirmed, cancelled, no_show, completed
        'notes',
        'reschedule_count',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
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

    public function consultation()
    {
        return $this->hasOne(Consultation::class);
    }
}
