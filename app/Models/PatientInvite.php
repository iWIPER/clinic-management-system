<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

class PatientInvite extends Model
{
    use BelongsToClinic;

    // cadastro = paciente novo; atualizacao = paciente já existente atualizando dados (BRD §3.4.1)
    const KINDS = ['cadastro', 'atualizacao'];

    const STATUSES = [
        'gerado', 'enviado', 'visualizado', 'em_preenchimento',
        'aguardando_conclusao', 'concluido', 'expirado', 'cancelado',
    ];

    // Únicos status que contam como "resolvidos" — fora deles, um convite é
    // ativo e entra na regra de unicidade (BRD §5.2, active_key na migration).
    const TERMINAL_STATUSES = ['concluido', 'expirado', 'cancelado'];

    // Etapas do wizard público sempre presentes (Fase 2, BRD §8). "convenio"
    // entra condicionalmente, ver wizardSteps() — não é fixo porque depende
    // de allow_insurance por convite. Anamnese (Fase 4) não entra aqui — é
    // um estágio pós-wizard controlado por status (aguardando_conclusao),
    // não por current_step, porque opera sobre um model diferente
    // (AnamnesisInstance/AnamnesisAnswer), com seus próprios endpoints.
    const BASE_WIZARD_STEPS = ['dados_pessoais', 'endereco', 'responsavel_legal'];

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'kind',
        'token',
        'status',
        'channel',
        'allow_insurance',
        'allow_anamnesis',
        'anamnesis_template_id',
        'anamnesis_instance_id',
        'expires_at',
        'progress',
        'current_step',
        'opened_at',
        'started_at',
        'completed_at',
        'anamnesis_completed_at',
        'not_responded_flagged_at',
        'cancelled_at',
        'cancelled_by',
        'created_by',
    ];

    protected $casts = [
        'allow_insurance'          => 'boolean',
        'allow_anamnesis'          => 'boolean',
        'progress'                 => 'integer',
        'expires_at'               => 'datetime',
        'opened_at'                => 'datetime',
        'started_at'               => 'datetime',
        'completed_at'             => 'datetime',
        'anamnesis_completed_at'   => 'datetime',
        'not_responded_flagged_at' => 'datetime',
        'cancelled_at'             => 'datetime',
    ];

    public function isActive(): bool
    {
        return ! in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Etapas que ESTE convite especificamente percorre — não uma lista fixa
     * (Fase 3, BRD §19): "convenio" só entra quando allow_insurance é
     * verdadeiro. Usado tanto para validar current_step quanto para calcular
     * progress — o denominador precisa refletir o que é alcançável por este
     * convite, senão um convite sem convênio nunca chegaria a 100%.
     */
    public function wizardSteps(): array
    {
        $steps = self::BASE_WIZARD_STEPS;

        if ($this->allow_insurance) {
            $steps[] = 'convenio';
        }

        return $steps;
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    // anamnesisTemplate() propositalmente ausente: PatientInviteService só
    // precisa do ID (AnamnesisService::createInstance() recebe um int, não
    // uma relação) — anamnesisInstance() abaixo é a única travessia real que
    // a Fase 4 usa.
    public function anamnesisInstance()
    {
        return $this->belongsTo(\App\Models\AnamnesisInstance::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function activityLogs()
    {
        return $this->hasMany(PatientInviteActivityLog::class);
    }
}
