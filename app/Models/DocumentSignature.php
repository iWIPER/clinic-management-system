<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DocumentSignature extends Model
{
    use BelongsToClinic;

    public const ROLE_PATIENT      = 'patient';
    public const ROLE_PROFESSIONAL = 'professional';
    public const ROLE_RESPONSIBLE  = 'responsible';
    public const ROLE_WITNESS      = 'witness';

    protected $fillable = [
        'clinic_id',
        'document_id',
        'signer_role',
        'signer_name',
        'signer_cpf',
        'signer_email',
        'professional_id',
        'professional_cro',
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

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
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

        return Storage::disk('public')->url($this->signature_path);
    }

    public function roleLabel(): string
    {
        return match ($this->signer_role) {
            self::ROLE_PATIENT      => 'Paciente',
            self::ROLE_PROFESSIONAL => 'Profissional',
            self::ROLE_RESPONSIBLE  => 'Responsável',
            self::ROLE_WITNESS      => 'Testemunha',
            default                 => ucfirst($this->signer_role),
        };
    }

    public function method(): string
    {
        return 'Eletrônica (canvas)';
    }
}
