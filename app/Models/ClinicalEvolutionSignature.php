<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalEvolutionSignature extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'evolution_id',
        'patient_name',
        'patient_cpf',
        'patient_email',
        'signature_path',
        'signature_hash',
        'ip_address',
        'user_agent',
        'timezone',
        'browser_info',
        'geolocation',
        'signed_at',
    ];

    protected $casts = [
        'browser_info' => 'array',
        'geolocation'  => 'array',
        'signed_at'    => 'datetime',
    ];

    public function evolution(): BelongsTo
    {
        return $this->belongsTo(ClinicalEvolution::class, 'evolution_id');
    }

    public function signatureUrl(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->signature_path);
    }
}
