<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sem controller/rota/upload ainda — só a estrutura, mesmo padrão de anexo
 * de patient_photos (arquivo no Google Drive da clínica).
 */
class TaskAttachment extends Model
{
    protected $fillable = [
        'task_id',
        'drive_file_id',
        'drive_folder_id',
        'filename',
        'mime_type',
        'size_bytes',
        'uploaded_by_id',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
