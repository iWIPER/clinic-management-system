<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnamnesisTemplateQuestion extends Model
{
    protected $fillable = [
        'template_id',
        'question_id',
        'sort_order',
        'is_required',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_required' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(AnamnesisTemplate::class, 'template_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AnamnesisQuestion::class, 'question_id');
    }
}