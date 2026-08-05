<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnamnesisAnswer extends Model
{
    protected $fillable = [
        'instance_id',
        'question_id',
        'question_text',
        'question_type',
        'value',
        'supplementary_text',
        'file_path',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(AnamnesisInstance::class, 'instance_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AnamnesisQuestion::class, 'question_id');
    }
}