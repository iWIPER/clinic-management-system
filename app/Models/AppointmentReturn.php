<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

class AppointmentReturn extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'appointment_id',
        'patient_id',
        'professional_id',
        'due_date',
        'reason',
        'status', // pending, scheduled, dismissed
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function professional()
    {
        return $this->belongsTo(User::class, 'professional_id');
    }
}
