<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Duas linhagens de registro convivem nesta tabela: as 2 fixas por usuário
 * (`key` = 'mine'/'team' — preferência pessoal de nome/cor sobre um bucket
 * computado) e os escopos personalizados (`key` nulo — uma entidade real e
 * compartilhada da clínica, não por usuário; `user_id` aqui vira "quem criou
 * e administra"). Ver TaskListController para as regras de cada tipo.
 */
class TaskList extends Model
{
    public const SHARING_PRIVATE  = 'private';
    public const SHARING_TEAM     = 'team';
    public const SHARING_SELECTED = 'selected';

    protected $fillable = [
        'clinic_id',
        'user_id',
        'key',
        'name',
        'color',
        'sharing_type',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sharedWith(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_list_shares');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Só usado pelos escopos personalizados (key nulo) — "private" nos
     * fixos não passa por aqui, porque eles nunca aparecem pra outro usuário
     * de qualquer forma (cada um tem a própria linha em `mine`/`team`).
     */
    public function scopeAccessibleBy(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhere('sharing_type', self::SHARING_TEAM)
                ->orWhere(function (Builder $q2) use ($userId) {
                    $q2->where('sharing_type', self::SHARING_SELECTED)
                        ->whereHas('sharedWith', fn (Builder $q3) => $q3->where('users.id', $userId));
                });
        });
    }

    public function isAccessibleBy(int $userId): bool
    {
        return static::query()->accessibleBy($userId)->whereKey($this->id)->exists();
    }

    public function present(int $currentUserId): array
    {
        return [
            'id'              => $this->id,
            'key'             => $this->key,
            'name'            => $this->name,
            'color'           => $this->color,
            'sharing_type'    => $this->sharing_type,
            'shared_user_ids' => $this->sharedWith()->pluck('users.id'),
            'is_owner'        => (int) $this->user_id === $currentUserId,
            'task_count'      => $this->key === null ? $this->tasks()->count() : null,
        ];
    }
}
