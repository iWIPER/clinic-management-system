<?php

namespace App\Models;

use App\Enums\Anamnesis\InstanceStatus;
use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnamnesisInstance extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'template_id',
        'template_name',
        'custom_name',
        'template_version',
        'professional_id',
        'status',
        'progress',
        'started_at',
        'anamnesis_date',
        'completed_at',
        'signed_at',
        'pdf_path',
        'validation_token',
        'disabled_question_ids',
    ];

    protected $casts = [
        'template_version' => 'integer',
        'progress' => 'integer',
        'started_at' => 'datetime',
        'anamnesis_date' => 'datetime',
        'completed_at' => 'datetime',
        'signed_at' => 'datetime',
        'disabled_question_ids' => 'array',
    ];

    public function displayName(): string
    {
        return $this->custom_name ?: $this->template_name;
    }

    public function effectiveDate(): \Carbon\Carbon
    {
        return $this->anamnesis_date ?? $this->created_at;
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AnamnesisTemplate::class, 'template_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AnamnesisAnswer::class, 'instance_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AnamnesisAlert::class, 'instance_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(AnamnesisActivityLog::class, 'instance_id');
    }

    // Mantida para retrocompatibilidade — aponta para a assinatura do paciente
    public function signature(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AnamnesisSignature::class, 'instance_id')
            ->where('signer_type', 'patient');
    }

    public function patientSignature(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AnamnesisSignature::class, 'instance_id')
            ->where('signer_type', 'patient');
    }

    public function dentistSignature(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AnamnesisSignature::class, 'instance_id')
            ->where('signer_type', 'dentist');
    }

    public function isSigned(): bool
    {
        return in_array($this->status, [
            InstanceStatus::Signed->value,
            InstanceStatus::FullySigned->value,
        ]);
    }

    public function isFullySigned(): bool
    {
        return $this->status === InstanceStatus::FullySigned->value;
    }

    public function statusEnum(): InstanceStatus
    {
        return InstanceStatus::from($this->status);
    }
}