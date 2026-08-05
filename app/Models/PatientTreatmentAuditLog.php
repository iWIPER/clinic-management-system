<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

class PatientTreatmentAuditLog extends Model
{
    use BelongsToClinic;

    public $timestamps = false;

    protected $fillable = [
        'clinic_id',
        'patient_treatment_id',
        'user_id',
        'action',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public const ACTIONS = [
        'created'                => 'Criação',
        'updated'                => 'Edição',
        'cost_changed'           => 'Alteração de custo',
        'professional_changed'   => 'Troca de profissional',
        'completed'              => 'Conclusão',
        'cancelled'              => 'Cancelamento',
        'duplicated'             => 'Duplicação',
    ];

    public function patientTreatment()
    {
        return $this->belongsTo(PatientTreatment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
