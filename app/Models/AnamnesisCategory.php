<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnamnesisCategory extends Model
{
    protected $fillable = [
        'template_id',
        'name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(AnamnesisTemplate::class, 'template_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AnamnesisQuestion::class, 'category_id')->orderBy('sort_order');
    }
}