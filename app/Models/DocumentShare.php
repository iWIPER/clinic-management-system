<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class DocumentShare extends Model
{
    use BelongsToClinic;

    const STATUS_PENDING = 'pending';
    const STATUS_VIEWED = 'viewed';
    const STATUS_EXPIRED = 'expired';
    const STATUS_REVOKED = 'revoked';

    // Fase B5: geração/envio assíncrono (PDF protegido + S3 + e-mail) —
    // independente de STATUS_* acima, que é o ciclo de vida do link do lado
    // do destinatário.
    const GENERATION_PROCESSING = 'processing';
    const GENERATION_SENT = 'sent';
    const GENERATION_FAILED = 'failed';

    const MAX_IDENTITY_ATTEMPTS = 5;
    const IDENTITY_LOCK_MINUTES = 15;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'shareable_type',
        'shareable_id',
        'token',
        'recipient_email',
        'recipient_name',
        'friendly_filename',
        'storage_path',
        'password_encrypted',
        'password_revealed_at',
        'identity_attempts',
        'identity_locked_until',
        'status',
        'generation_status',
        'generation_failed_reason',
        'sent_at',
        'expires_at',
        'revoked_at',
        'created_by_id',
    ];

    protected $casts = [
        'password_encrypted'    => 'encrypted',
        'password_revealed_at'  => 'datetime',
        'identity_locked_until' => 'datetime',
        'sent_at'                => 'datetime',
        'expires_at'             => 'datetime',
        'revoked_at'             => 'datetime',
    ];

    protected $hidden = [
        'password_encrypted',
    ];

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DocumentShareLog::class);
    }

    public static function generateToken(): string
    {
        // Aleatório, não incremental, alta entropia — nunca usar o ID.
        return Str::random(48);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isIdentityLocked(): bool
    {
        return $this->identity_locked_until !== null && $this->identity_locked_until->isFuture();
    }

    public function isUsable(): bool
    {
        return ! $this->isExpired() && ! $this->isRevoked();
    }

    public function displayTitle(): string
    {
        $shareable = $this->shareable;

        return match (true) {
            $shareable instanceof Document => $shareable->template_name,
            $shareable instanceof AnamnesisInstance => $shareable->displayName(),
            default => $this->friendly_filename,
        };
    }
}
