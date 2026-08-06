<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Patient extends Model
{
    use HasFactory, BelongsToClinic;

    protected static function boot()
    {
        parent::boot();

        // patient_invites.patient_id não tem ON DELETE CASCADE no banco — o
        // MySQL recusa essa ação numa coluna da qual uma coluna gerada
        // depende (ver comentário na migration de patient_invites). O cascade
        // é feito aqui, mesmo padrão de hook de ciclo de vida já usado em
        // Invite::boot() (só que para creating, não deleting).
        static::deleting(function (Patient $patient) {
            $patient->patientInvites()->delete();
        });
    }

    protected $fillable = [
        'clinic_id',
        'nome',
        'sobrenome',
        'nascimento',
        'sexo',
        'status',             // ativo, inativo, falecido
        'status_automatico',  // true = sistema calcula; false = manual
        'doc_tipo',           // legado — não usado pelo formulário (ver cpf/rg/passaporte)
        'doc_numero',         // legado — idem
        'cpf',
        'rg',
        'passaporte',
        'is_estrangeiro',
        'profissao',
        'canal_lembrete',     // whatsapp, sms, email, nao_enviar — só armazena, sem envio implementado
        'telefone',
        'email',
        'possui_responsavel_legal',
        'responsavel_legal_nome',
        'responsavel_legal_cpf',
        'responsavel_legal_rg',
        'responsavel_legal_estrangeiro',
        'responsavel_legal_passaporte',
        'responsavel_legal_telefone',
        'responsavel_legal_parentesco',
        'contato_emergencia_nome',
        'contato_emergencia_telefone',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'estado',
        'drive_folder_id',    // Google Drive do paciente (criado no 1º upload)
        'responsible_professional_id', // manual — nunca alterado automaticamente pelo histórico
        'created_by_id',
        'updated_by_id',
        'origem',             // manual, indicacao, google, instagram, facebook, site, whatsapp, outro
        'convenio_id',
        'tipo_atendimento',   // particular, convenio, outro
        'convenio_numero_carteirinha',
        'convenio_titular',
        'convenio_titular_cpf',
        'convenio_titular_parentesco',
        'tipo_atendimento_outro_descricao',
    ];

    protected $casts = [
        'nascimento'       => 'date',
        'status_automatico' => 'boolean',
        'is_estrangeiro' => 'boolean',
        'possui_responsavel_legal' => 'boolean',
        'responsavel_legal_estrangeiro' => 'boolean',
    ];

    public function getNomeCompletoAttribute(): string
    {
        return trim($this->nome . ' ' . $this->sobrenome);
    }

    public function getIdadeAttribute(): ?int
    {
        return $this->nascimento?->age;
    }

    /**
     * Mesma prioridade usada em Patients/Show.vue (DOCUMENT_TYPES) — CPF >
     * RG > Passaporte. Precisa ser repetida aqui porque não há como
     * compartilhar lógica entre PHP e JS; ao adicionar um novo tipo de
     * documento (RNE, DNI, NIE...), atualizar as duas listas.
     */
    public const DOCUMENT_FIELDS = ['cpf' => 'CPF', 'rg' => 'RG', 'passaporte' => 'Passaporte'];

    public function documentInfo(): array
    {
        return static::resolveDocument([
            'cpf' => $this->cpf,
            'rg' => $this->rg,
            'passaporte' => $this->passaporte,
        ]);
    }

    public function guardianDocumentInfo(): array
    {
        return static::resolveDocument([
            'cpf' => $this->responsavel_legal_cpf,
            'rg' => $this->responsavel_legal_rg,
            'passaporte' => $this->responsavel_legal_passaporte,
        ]);
    }

    private static function resolveDocument(array $values): array
    {
        foreach (static::DOCUMENT_FIELDS as $field => $label) {
            if (! empty($values[$field])) {
                return ['type' => $label, 'number' => $values[$field]];
            }
        }

        return ['type' => null, 'number' => null];
    }

    public function responsibleProfessional()
    {
        return $this->belongsTo(User::class, 'responsible_professional_id');
    }

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }

    public function treatments()
    {
        return $this->hasMany(PatientTreatment::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    public function photos()
    {
        return $this->hasMany(PatientPhoto::class);
    }

    public function clinicalRecords()
    {
        return $this->hasMany(ClinicalRecord::class);
    }

    public function anamnesis()
    {
        return $this->hasOne(PatientAnamnesis::class);
    }

    public function evolutions()
    {
        return $this->hasMany(ClinicalEvolution::class);
    }

    public function odontogram()
    {
        return $this->hasOne(PatientOdontogram::class);
    }

    public function anamnesisInstances()
    {
        return $this->hasMany(AnamnesisInstance::class);
    }

    public function anamnesisAlerts()
    {
        return $this->hasMany(AnamnesisAlert::class)->where('is_active', true);
    }

    public function notes()
    {
        return $this->hasMany(PatientNote::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function markers(): BelongsToMany
    {
        return $this->belongsToMany(PatientTag::class, 'patient_marker_assignments')->markers();
    }

    public function patientInvites()
    {
        return $this->hasMany(PatientInvite::class);
    }
}
