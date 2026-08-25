<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcedureExecution extends Model
{
    use HasFactory, BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'appointment_id',
        'treatment_id',
        'executed_at',
        'price_charged',
        'notes',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
        'price_charged' => 'decimal:2',
    ];

    public function appointment() { return $this->belongsTo(Appointment::class); }
    public function treatment() { return $this->belongsTo(Treatment::class); }
}
