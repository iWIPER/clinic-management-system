<?php

namespace App\Models;

use App\Enums\Documents\DocumentStatus;
use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Document extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'template_id',
        'template_version_id',
        'template_name',
        'professional_id',
        'status',
        'rendered_html',
        'pdf_path',
        'validation_token',
        'signature_token',
        'signature_token_expires_at',
        'issued_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
        'expires_at',
        'document_code',
        'content_hash',
        'created_by_id',
    ];

    protected $casts = [
        'signature_token_expires_at' => 'datetime',
        'issued_at'                  => 'datetime',
        'completed_at'                => 'datetime',
        'cancelled_at'                => 'datetime',
        'expires_at'                  => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplateVersion::class, 'template_version_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(DocumentSignature::class, 'document_id');
    }

    public function patientSignature(): HasOne
    {
        return $this->hasOne(DocumentSignature::class, 'document_id')->where('signer_role', DocumentSignature::ROLE_PATIENT);
    }

    public function professionalSignature(): HasOne
    {
        return $this->hasOne(DocumentSignature::class, 'document_id')->where('signer_role', DocumentSignature::ROLE_PROFESSIONAL);
    }

    public function responsibleSignature(): HasOne
    {
        return $this->hasOne(DocumentSignature::class, 'document_id')->where('signer_role', DocumentSignature::ROLE_RESPONSIBLE);
    }

    public function witnessSignatures(): HasMany
    {
        return $this->hasMany(DocumentSignature::class, 'document_id')->where('signer_role', DocumentSignature::ROLE_WITNESS);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(DocumentActivityLog::class, 'document_id');
    }

    public function relatedTreatments(): MorphToMany
    {
        return $this->morphedByMany(Treatment::class, 'related', 'document_relations');
    }

    public function relatedBudgets(): MorphToMany
    {
        return $this->morphedByMany(Budget::class, 'related', 'document_relations');
    }

    public function statusEnum(): DocumentStatus
    {
        return DocumentStatus::from($this->status);
    }

    public function requiredSignerRoles(): array
    {
        return $this->template?->requiredSignerRoles() ?? [];
    }

    public function pendingSignerRoles(): array
    {
        $signed = $this->signatures()->pluck('signer_role')->unique()->all();

        return array_values(array_diff($this->requiredSignerRoles(), $signed));
    }

    public function isFullySigned(): bool
    {
        return empty($this->pendingSignerRoles());
    }

    public function isCancelled(): bool
    {
        return $this->status === DocumentStatus::Cancelled->value;
    }
}
