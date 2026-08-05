<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplateVersion extends Model
{
    protected $fillable = [
        'template_id',
        'version',
        'title',
        'content_html',
        'change_summary',
        'created_by_id',
        'is_archived',
    ];

    protected $casts = [
        'version'     => 'integer',
        'is_archived' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'template_version_id');
    }
}
