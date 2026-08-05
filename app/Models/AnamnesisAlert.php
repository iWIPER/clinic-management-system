<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnamnesisAlert extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'instance_id',
        'answer_id',
        'question_id',
        'label',
        'detail',
        'question_text',
        'answer_value',
        'professional_id',
        'is_active',
        'triggered_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'triggered_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(AnamnesisInstance::class, 'instance_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }
}