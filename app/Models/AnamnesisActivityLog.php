<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnamnesisActivityLog extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'instance_id',
        'patient_id',
        'template_id',
        'action',
        'user_id',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(AnamnesisInstance::class, 'instance_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}