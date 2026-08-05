<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnamnesisSignature extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'instance_id',
        'signer_type',
        'professional_id',
        'professional_cro',
        'patient_name',
        'patient_cpf',
        'patient_email',
        'google_id',
        'google_name',
        'google_email',
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

    public function instance(): BelongsTo
    {
        return $this->belongsTo(AnamnesisInstance::class, 'instance_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function signatureUrl(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->signature_path);
    }

    public function method(): string
    {
        return $this->google_id ? 'Google' : 'Presencial';
    }
}
