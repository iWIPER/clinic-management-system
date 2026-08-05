<?php

namespace App\Models;

use App\Enums\Anamnesis\QuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnamnesisQuestion extends Model
{
    protected $fillable = [
        'clinic_id',
        'instance_id',
        'category_id',
        'category',
        'text',
        'description',
        'supplementary_placeholder',
        'type',
        'is_required',
        'has_alert',
        'alert_text',
        'alert_trigger_values',
        'show_on_patient_card',
        'is_active',
        'question_hash',
        'options',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'has_alert' => 'boolean',
        'show_on_patient_card' => 'boolean',
        'is_active' => 'boolean',
        'alert_trigger_values' => 'array',
        'options' => 'array',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function categoryDefinition(): BelongsTo
    {
        return $this->belongsTo(AnamnesisCategoryDefinition::class, 'category_id');
    }

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(AnamnesisTemplate::class, 'anamnesis_template_questions', 'question_id', 'template_id')
            ->withPivot(['sort_order', 'is_required'])
            ->withTimestamps();
    }

    public function templateLinks(): HasMany
    {
        return $this->hasMany(AnamnesisTemplateQuestion::class, 'question_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AnamnesisAnswer::class, 'question_id');
    }

    public function typeEnum(): QuestionType
    {
        return QuestionType::from($this->type);
    }

    public function shouldTriggerAlert(?string $value, ?string $supplementary = null): bool
    {
        if (! $this->has_alert || ! $value) {
            return false;
        }

        $normalized = mb_strtolower(trim($value));
        $triggers = $this->alert_trigger_values ?? ['sim'];

        if (! in_array($normalized, array_map('mb_strtolower', $triggers), true)) {
            return false;
        }

        if ($this->alert_text && str_contains(mb_strtolower($this->alert_text), 'alérgico a')) {
            return $normalized === 'sim' && filled($supplementary);
        }

        return true;
    }

    public function resolvedAlertLabel(?string $supplementary = null): ?string
    {
        if (! $this->has_alert) {
            return null;
        }

        $text = $this->alert_text;

        if (! $text) {
            return $supplementary ?: 'Alerta clínico';
        }

        if (str_contains(mb_strtolower($text), 'alérgico a') && $supplementary) {
            return trim($text) . ' ' . $supplementary;
        }

        return $text;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(AnamnesisInstance::class, 'instance_id');
    }

    public function scopeForClinic($query, ?int $clinicId)
    {
        return $query
            ->whereNull('instance_id')
            ->where(function ($q) use ($clinicId) {
                $q->whereNull('clinic_id');
                if ($clinicId) {
                    $q->orWhere('clinic_id', $clinicId);
                }
            });
    }

    public function isRenderable(): bool
    {
        $text = trim($this->text);

        if ($text === '') {
            return false;
        }

        $patterns = [
            '/^(TYPE|ALERT|CATEGORY|MODEL|SHOW_ON_PATIENT_CARD)\s*:/i',
            '/^-?\s*Alerta\s*:/iu',
            '/^Pergunta\s+(Sim|Somente)/iu',
            '/^(Sem alerta|Com alerta)/iu',
            '/^(YES_NO_UNKNOWN_TEXT|YES_NO_UNKNOWN|YES_NO_TEXT|YES_NO|TEXT)$/i',
            '/^(Disponível na ficha|Não aparece|Não disponível)/iu',
            '/^Checkpoint$/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return false;
            }
        }

        return true;
    }
}