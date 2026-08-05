<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnamnesisCategoryDefinition extends Model
{
    protected $fillable = [
        'clinic_id',
        'name',
        'slug',
        'icon',
        'icon_color',
        'description',
        'sort_order',
        'is_active',
        'is_system',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AnamnesisQuestion::class, 'category_id');
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