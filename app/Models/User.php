<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles; // teams feature ativa

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'account_type',
        'invited_by_admin_id',
        'phone',
        'cpf',
        'birth_date',
        'gender',
        'cro',
        'cro_uf',
        'specialty',
        'job_title',
        'status',
        'profile_photo_path',
        'last_login_at',
        'profile_updated_at',
        'preferences',
        'google_id',
        'apple_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at'    => 'datetime',
        'birth_date'           => 'date',
        'last_login_at'        => 'datetime',
        'profile_updated_at'   => 'datetime',
        'preferences'          => 'array',
        'password'             => 'hashed',
    ];

    public function isAffiliate(): bool
    {
        return $this->account_type === 'affiliate';
    }

    /**
     * Todas as clínicas que o usuário participa (N:N)
     * Importante: NUNCA adicionar clinic_id direto em users.
     */
    public function clinics()
    {
        return $this->belongsToMany(Clinic::class, 'clinic_user')
                    ->withPivot('role', 'drive_doctor_folder_id', 'agenda_visible_to_team', 'working_days', 'working_start', 'working_end')
                    ->withTimestamps()
                    ->using(ClinicUserPivot::class);
    }

    /**
     * Membros com agenda de atendimento própria numa clínica: cargo clínico
     * (Dentista/Dentista Administrador) OU dono da clínica — o dono muitas
     * vezes é o próprio profissional principal e pode nunca ter passado pelo
     * fluxo de convite (job_title vazio), então cai aqui de qualquer forma.
     * Única fonte dessa regra — usada pela sidebar da Agenda e por
     * Configurações > Agendas.
     */
    public function scopeClinicalProfessionalsOf($query, int $clinicId)
    {
        return $query->whereHas('clinics', function ($q) use ($clinicId) {
            $q->where('clinics.id', $clinicId)
              ->where(function ($q2) {
                  $q2->whereIn('users.job_title', Invite::CLINICAL_JOB_TITLES)
                     ->orWhere('clinic_user.role', 'owner');
              });
        });
    }

    /**
     * Clínica ativa atual (geralmente setada no middleware de tenant)
     */
    public function currentClinic()
    {
        // Implementação simples via session ou contexto
        $clinicId = session('current_clinic_id');
        return $clinicId ? $this->clinics()->where('clinics.id', $clinicId)->first() : null;
    }

    /**
     * Papel do usuário na clínica atual
     */
    public function roleInCurrentClinic(): ?string
    {
        $clinic = $this->currentClinic();
        if (!$clinic) return null;
        return $this->clinics()->where('clinics.id', $clinic->id)->first()?->pivot->role;
    }

    public function isOwnerOf(Clinic $clinic): bool
    {
        return $this->clinics()
            ->where('clinics.id', $clinic->id)
            ->wherePivot('role', 'owner')
            ->exists();
    }

    /**
     * Camada global da plataforma, acima do nível de clínica — não depende
     * de current_clinic_id nem de role owner/admin de clínica. Fonte única:
     * tabela system_admins (substitui o antigo e-mail hardcoded em
     * SuperAdmin::EMAIL).
     */
    public function isSystemAdmin(): bool
    {
        return $this->systemAdminGrant()->exists();
    }

    public function systemAdminGrant()
    {
        return $this->hasOne(SystemAdmin::class)->active()->latest('granted_at');
    }

    /**
     * Pivot (role, agenda_visible_to_team, working_days...) do vínculo deste
     * usuário com uma clínica específica — evita repetir a mesma consulta
     * em cada controller que precisa ler configuração de agenda.
     */
    public function clinicPivotFor(int $clinicId): ?ClinicUserPivot
    {
        return $this->clinics()->where('clinics.id', $clinicId)->first()?->pivot;
    }

    /**
     * Um profissional sempre vê a própria agenda; a de outro só se ele
     * ativou "Disponibilizar minha agenda para a equipe". Autoridade real
     * dessa regra — usada tanto pra montar a lista quanto pra bloquear
     * acesso direto via professional_id na URL (ver AppointmentController).
     */
    public function canViewAgendaOf(int $targetUserId, int $clinicId): bool
    {
        if ($this->id === $targetUserId) {
            return true;
        }

        $target = static::find($targetUserId);

        return (bool) $target?->clinicPivotFor($clinicId)?->agenda_visible_to_team;
    }
}
