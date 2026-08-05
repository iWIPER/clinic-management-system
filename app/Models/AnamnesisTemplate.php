<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnamnesisTemplate extends Model
{
    protected $fillable = [
        'clinic_id',
        'name',
        'slug',
        'description',
        'version',
        'is_system',
        'is_active',
        'is_default',
        'sort_order',
        'created_by_id',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'version' => 'integer',
        'sort_order' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function templateQuestions(): HasMany
    {
        return $this->hasMany(AnamnesisTemplateQuestion::class, 'template_id')->orderBy('sort_order');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(AnamnesisQuestion::class, 'anamnesis_template_questions', 'template_id', 'question_id')
            ->withPivot(['sort_order', 'is_required'])
            ->orderByPivot('sort_order');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(AnamnesisInstance::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForClinic($query, ?int $clinicId)
    {
        return $query->where(function ($q) use ($clinicId) {
            $q->whereNull('clinic_id');
            if ($clinicId) {
                $q->orWhere('clinic_id', $clinicId);
            }
        });
    }
}