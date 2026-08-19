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

    // Labels apresentados ao usuário em toda a UI de Tarefas (Lista, Board,
    // tooltips, formulário) — única fonte, pra Lista e Board nunca divergirem
    // no texto. 'waiting' é um estado legado (não é mais oferecido como
    // opção nova, ver KANBAN_STATUSES) mantido aqui só pra tarefas antigas
    // que já têm esse valor continuarem exibindo um label correto em vez de
    // caírem no fallback (a própria chave crua) — ver Task::statusLabel().
    public const STATUSES = [
        'todo'    => 'A Fazer',
        'doing'   => 'Fazendo',
        'waiting' => 'Aguardando',
        'done'    => 'Feito',
    ];

    // As 3 colunas REAIS do board Kanban — e as únicas 3 opções oferecidas
    // no formulário de criação/edição (ver TaskController::index() e
    // TaskFormModal.vue). Não inclui 'waiting': tarefas antigas com esse
    // status continuam válidas e visíveis (Lista, tooltip, contagem), mas
    // não é mais um estado que o usuário escolhe ou vê como coluna — no
    // Kanban elas aparecem agrupadas em "Fazendo" (ver kanbanColumnOf() no
    // frontend, TaskPanel.vue).
    public const KANBAN_STATUSES = [
        'todo'  => 'A Fazer',
        'doing' => 'Fazendo',
        'done'  => 'Feito',
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
