<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

class PatientInviteActivityLog extends Model
{
    use BelongsToClinic;

    public $timestamps = false;

    protected $fillable = [
        'clinic_id',
        'patient_invite_id',
        'action',
        'metadata',
        'actor_type',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function invite()
    {
        return $this->belongsTo(PatientInvite::class, 'patient_invite_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
