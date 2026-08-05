<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PatientTreatment extends Model
{
    use BelongsToClinic;

    public const STATUS_EM_ANDAMENTO = 'em_andamento';
    public const STATUS_FUTURO       = 'futuro';
    public const STATUS_CONCLUIDO    = 'concluido';

    public const STATUSES = [
        self::STATUS_EM_ANDAMENTO => 'Em andamento',
        self::STATUS_FUTURO       => 'Futuro',
        self::STATUS_CONCLUIDO    => 'Finalizado',
    ];

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'treatment_id',
        'procedure_name',
        'professional_id',
        'convenio_id',
        'budget_code',
        'tooth',
        'faces',
        'value_charged',
        'cost',
        'status',
        'treatment_date',
        'completed_at',
        'notes',
        'stock_updated_at',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'faces'            => 'array',
        'value_charged'    => 'decimal:2',
        'cost'             => 'decimal:2',
        'treatment_date'   => 'date',
        'completed_at'     => 'datetime',
        'stock_updated_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function professional()
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(PatientTreatmentAuditLog::class)->orderByDesc('created_at');
    }

    public function evolutions()
    {
        return $this->hasMany(ClinicalEvolution::class);
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_CONCLUIDO;
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForTooth(Builder $query, string $tooth): Builder
    {
        return $query->where('tooth', $tooth);
    }

    /**
     * Mapa dente (FDI) → tratamentos ativos, no formato consumido pelo
     * overlay do odontograma (cor/badge em `OdontogramChart.vue::visualStatus()`)
     * e pelo tooltip/histórico por dente (ToothHistoryModal, OdontogramTooltip).
     */
    public static function groupedByTooth(int $patientId): array
    {
        return static::where('patient_id', $patientId)
            ->whereNotNull('tooth')
            ->with('professional:id,name')
            ->orderByDesc('treatment_date')
            ->get(['id', 'tooth', 'status', 'faces', 'procedure_name', 'professional_id', 'treatment_date', 'completed_at', 'value_charged', 'budget_code'])
            ->groupBy('tooth')
            ->map(fn ($group) => $group->map(fn (self $t) => [
                'id'             => $t->id,
                'status'         => $t->status,
                'faces'          => $t->faces,
                'procedure_name' => $t->procedure_name,
                'professional'   => $t->professional ? ['name' => $t->professional->name] : null,
                'treatment_date' => optional($t->treatment_date)->toDateString(),
                'completed_at'   => optional($t->completed_at)->toIso8601String(),
                'value_charged'  => (float) $t->value_charged,
                'budget_code'    => $t->budget_code,
            ])->values())
            ->all();
    }

    public static function nextBudgetCode(int $clinicId, \DateTimeInterface $date): string
    {
        $prefix = 'TRT-' . $date->format('ymd') . '-';

        $count = static::withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->where('budget_code', 'like', $prefix . '%')
            ->count();

        return $prefix . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
