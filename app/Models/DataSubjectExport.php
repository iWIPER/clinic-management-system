<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataSubjectExport extends Model
{
    const SUBJECT_USER = 'user';
    const SUBJECT_PATIENT = 'patient';

    const GENERATION_PROCESSING = 'processing';
    const GENERATION_READY = 'ready';
    const GENERATION_FAILED = 'failed';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'clinic_id',
        'requested_by_id',
        'generation_status',
        'generation_failed_reason',
        'storage_path',
        'file_size',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
