<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invite extends Model
{
    protected $fillable = [
        'clinic_id', 'type', 'email', 'name', 'role', 'job_title',
        'token', 'short_token', 'expires_at', 'status', 'invited_by_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    // Regra de segurança do backend, não só informativa — nenhum convite de
    // equipe pode ficar válido por mais de 7 dias, independente do que um
    // chamador tente passar em expires_at (ver boot(), evento saving).
    const MAX_VALIDITY_DAYS = 7;

    // Cargos disponíveis no sistema — lista única, usada tanto no convite da
    // equipe quanto no campo "Cargo na clínica" do perfil do usuário. Não deve
    // haver campo livre em nenhuma tela: sempre selecionar a partir daqui.
    const JOB_TITLES = [
        'Dentista', 'Dentista Administrador', 'Secretário(a)', 'Administrador', 'Outro',
    ];

    // Mapeamento cargo → nível de permissão
    const JOB_TITLE_ROLES = [
        'Administrador'           => 'admin',
        'Dentista'                => 'professional',
        'Dentista Administrador'  => 'admin',
        'Secretário(a)'           => 'staff',
        'Outro'                   => 'staff',
    ];

    // Cargos que têm agenda de atendimento própria — usado pra decidir quem
    // aparece na seção "Agendas" da Agenda e na aba Configurações > Agendas.
    // Ver também User::scopeClinicalProfessionals().
    const CLINICAL_JOB_TITLES = ['Dentista', 'Dentista Administrador'];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($invite) {
            if (empty($invite->token)) {
                $invite->token = Str::random(32);
            }
            if (empty($invite->short_token)) {
                $invite->short_token = self::generateShortToken();
            }
            if (empty($invite->status)) {
                $invite->status = 'pending';
            }
            if (empty($invite->type)) {
                $invite->type = 'team';
            }
            // Auto-map job_title → role se não fornecido
            if (empty($invite->role) && !empty($invite->job_title)) {
                $invite->role = self::JOB_TITLE_ROLES[$invite->job_title] ?? 'staff';
            }
        });

        // Autoridade única sobre expires_at — roda em create E update, então
        // nenhum chamador (atual ou futuro) consegue conceder mais que
        // MAX_VALIDITY_DAYS, mesmo passando um valor explícito maior.
        static::saving(function ($invite) {
            $maxExpiry = now()->addDays(self::MAX_VALIDITY_DAYS);

            if (empty($invite->expires_at) || $invite->expires_at->greaterThan($maxExpiry)) {
                $invite->expires_at = $maxExpiry;
            }
        });
    }

    // ── Gera token curto único (AAA-999 ou AAA-AAA) ───────────────────────
    public static function generateShortToken(): string
    {
        $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // sem I e O para evitar confusão

        do {
            $p1 = $alpha[random_int(0, 23)] . $alpha[random_int(0, 23)] . $alpha[random_int(0, 23)];
            // 50% dígitos, 50% letras
            if (random_int(0, 1)) {
                $p2 = (string) random_int(100, 999);
            } else {
                $p2 = $alpha[random_int(0, 23)] . $alpha[random_int(0, 23)] . $alpha[random_int(0, 23)];
            }
            $token = "$p1-$p2";
        } while (self::where('short_token', $token)->exists());

        return $token;
    }

    // ── Relações ───────────────────────────────────────────────────────────
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────
    // Nunca confia só no expires_at armazenado — convites criados antes da
    // regra de MAX_VALIDITY_DAYS (ou qualquer linha legada com validade
    // maior) são tratados como expirados a partir de created_at + 7 dias,
    // sem precisar reescrever dado histórico nenhum.
    public function isExpired(): bool
    {
        $effectiveExpiry = $this->expires_at->min(
            ($this->created_at ?? now())->copy()->addDays(self::MAX_VALIDITY_DAYS)
        );

        return $effectiveExpiry->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && ! $this->isExpired();
    }

    public function isAffiliateInvite(): bool
    {
        return $this->type === 'affiliate';
    }

    // ── Aceite do convite (preserva registro para auditoria) ──────────────
    public function accept(User $user): void
    {
        if ($this->isAffiliateInvite()) {
            // Afiliado puro — sem clínica, sem onboarding.
            $user->update([
                'account_type'        => 'affiliate',
                'invited_by_admin_id' => $this->invited_by_id,
            ]);

            $this->update(['status' => 'accepted']);

            return;
        }

        // Evita duplicar membro
        if (! $this->clinic->users()->where('users.id', $user->id)->exists()) {
            $this->clinic->users()->attach($user->id, [
                'role' => $this->role ?? 'staff',
            ]);
        }

        if (!empty($this->job_title)) {
            $user->update(['job_title' => $this->job_title]);
        }

        session(['current_clinic_id' => $this->clinic_id]);

        $this->update(['status' => 'accepted']);
    }
}
