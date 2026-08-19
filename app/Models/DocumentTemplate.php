<?php

namespace App\Models;

use App\Services\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'clinic_id',
        'category_id',
        'name',
        'slug',
        'description',
        'current_version_id',
        'requires_patient_signature',
        'requires_professional_signature',
        'requires_responsible_signature',
        'requires_witness_signature',
        'signature_expiration_hours',
        'is_system',
        'is_active',
        'is_default',
        'sort_order',
        'created_by_id',
    ];

    protected $casts = [
        'requires_patient_signature'      => 'boolean',
        'requires_professional_signature' => 'boolean',
        'requires_responsible_signature'  => 'boolean',
        'requires_witness_signature'      => 'boolean',
        'signature_expiration_hours'      => 'integer',
        'is_system'                       => 'boolean',
        'is_active'                       => 'boolean',
        'is_default'                      => 'boolean',
        'sort_order'                      => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplateVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentTemplateVersion::class, 'template_id')->orderByDesc('version');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForClinic(Builder $query, ?int $clinicId): Builder
    {
        return $query->where(function (Builder $q) use ($clinicId) {
            $q->whereNull('clinic_id');
            if ($clinicId) {
                $q->orWhere('clinic_id', $clinicId);
            }
        });
    }

    /**
     * Cria uma nova versão imutável do conteúdo e move o ponteiro current_version_id.
     * Nunca sobrescreve uma versão já existente.
     */
    public function createNewVersion(string $title, string $contentHtml, ?string $changeSummary, ?int $userId): DocumentTemplateVersion
    {
        $nextVersion = ((int) $this->versions()->max('version')) + 1;

        $version = $this->versions()->create([
            'version'        => $nextVersion,
            'title'          => $title,
            'content_html'   => HtmlSanitizer::richText($contentHtml),
            'change_summary' => $changeSummary,
            'created_by_id'  => $userId,
        ]);

        $this->update(['current_version_id' => $version->id]);

        return $version;
    }

    public function requiredSignerRoles(): array
    {
        $roles = [];

        if ($this->requires_patient_signature) {
            $roles[] = 'patient';
        }
        if ($this->requires_professional_signature) {
            $roles[] = 'professional';
        }
        if ($this->requires_responsible_signature) {
            $roles[] = 'responsible';
        }
        if ($this->requires_witness_signature) {
            $roles[] = 'witness';
        }

        return $roles;
    }
}
