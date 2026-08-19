<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentShareLog extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id',
        'document_share_id',
        'action',
        'user_id',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function share(): BelongsTo
    {
        return $this->belongsTo(DocumentShare::class, 'document_share_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
