<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use BelongsToClinic;

    // Também representa a futura coluna do board Kanban.
    public const STATUSES = [
        'todo'    => 'A fazer',
        'doing'   => 'Em andamento',
        'waiting' => 'Aguardando',
        'done'    => 'Concluída',
    ];

    public const PRIORITIES = [
        'baixa'  => 'Baixa',
        'media'  => 'Média',
        'alta'   => 'Alta',
        'urgente' => 'Urgente',
    ];

    protected $fillable = [
        'clinic_id',
        'title',
        'description',
        'status',
        'priority',
        'assigned_to',
        'patient_id',
        'task_list_id',
        'created_by',
        'due_date',
        'position',
        'pinned_at',
        'is_favorite',
        'completed_at',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'pinned_at'    => 'datetime',
        'is_favorite'  => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TaskLabel::class);
    }

    // Sem UI/controller ainda — ver TaskComment/TaskAttachment.
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }
}
