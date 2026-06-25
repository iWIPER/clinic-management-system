<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Treatment extends Model
{
    use HasFactory, BelongsToClinic;

    public const TIPOS_BOOKABLE = ['procedimento', 'variacao'];

    protected $fillable = [
        'clinic_id',
        'nome',
        'categoria',
        'tipo',
        'parent_id',
        'especialidade',
        'duracao_padrao',
        'preco_base',
        'descricao',
        'cor',
        'ordem',
        'catalog_slug',
        'ativo',
        'deactivated_at',
        'deactivated_by_id',
    ];

    protected $casts = [
        'preco_base' => 'decimal:2',
        'ativo' => 'boolean',
        'deactivated_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function scopeBookable(Builder $query): Builder
    {
        return $query->whereIn('tipo', self::TIPOS_BOOKABLE);
    }

    public function scopeForScheduling(Builder $query): Builder
    {
        return $query->active()->bookable();
    }

    public function isBookable(): bool
    {
        return in_array($this->tipo, self::TIPOS_BOOKABLE, true);
    }

    public function materials()
    {
        return $this->belongsToMany(InventoryItem::class, 'treatment_materials')
            ->withPivot('quantidade')
            ->withTimestamps();
    }

    public function parent()
    {
        return $this->belongsTo(Treatment::class, 'parent_id');
    }

    public function variations()
    {
        return $this->hasMany(Treatment::class, 'parent_id')->orderBy('ordem');
    }

    public function procedureExecutions()
    {
        return $this->hasMany(ProcedureExecution::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(TreatmentAuditLog::class)->orderByDesc('created_at');
    }

    public function deactivatedBy()
    {
        return $this->belongsTo(User::class, 'deactivated_by_id');
    }
}