<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriveActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'photo_id',
        'event_type',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function photo()
    {
        return $this->belongsTo(PatientPhoto::class, 'photo_id');
    }
}
