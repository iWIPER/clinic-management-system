<?php

namespace App\Services;

use App\Mail\TeamInviteMail;
use App\Models\AccessLog;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InviteService
{
    // ── Cenários de validação ──────────────────────────────────────────────
    const SCENARIO_MEMBER    = 'MEMBER';    // Já membro desta clínica
    const SCENARIO_PENDING   = 'PENDING';   // Convite pendente ativo
    const SCENARIO_EXPIRED   = 'EXPIRED';   // Convite pendente expirado
    const SCENARIO_CANCELLED = 'CANCELLED'; // Convite cancelado/aceito — pode criar novo
    const SCENARIO_NEW       = 'NEW';       // Sem registro algum

    // ── Verificação de cenário (sem side-effects) ──────────────────────────
    public function checkScenario(string $email, int $clinicId): array
    {
        // 1. Já é membro desta clínica?
        $isMember = User::where('email', $email)
            ->whereHas('clinics', fn ($q) => $q->where('clinics.id', $clinicId))
            ->exists();

        if ($isMember) {
            $user = User::where('email', $email)->first();
            return [
                'scenario'    => self::SCENARIO_MEMBER,
                'invite'      => null,
                'system_user' => $user ? self::formatUser($user) : null,
            ];
        }

        // 2. Existe convite para este email nesta clínica?
        $invite = Invite::where('clinic_id', $clinicId)
            ->where('email', $email)
            ->with('invitedBy:id,name')
            ->latest('updated_at')
            ->first();

        // Usuário existe no sistema mas não nesta clínica (info extra)
        $systemUser = User::where('email', $email)->first();
        $systemUserData = $systemUser ? self::formatUser($systemUser) : null;

        if ($invite) {
            if ($invite->status === 'pending' && ! $invite->isExpired()) {
                return [
                    'scenario'    => self::SCENARIO_PENDING,
                    'invite'      => self::formatInvite($invite),
                    'system_user' => $systemUserData,
                ];
            }

            if ($invite->status === 'pending' && $invite->isExpired()) {
                return [
                    'scenario'    => self::SCENARIO_EXPIRED,
                    'invite'      => self::formatInvite($invite),
                    'system_user' => $systemUserData,
                ];
            }

            // status = accepted ou cancelled
            return [
                'scenario'    => self::SCENARIO_CANCELLED,
                'invite'      => self::formatInvite($invite),
                'system_user' => $systemUserData,
            ];
        }

        return [
            'scenario'    => self::SCENARIO_NEW,
            'invite'      => null,
            'system_user' => $systemUserData,
        ];
    }

    // ── Criar ou atualizar convite (respeita UNIQUE clinic_id+email) ───────
    public function createOrUpdate(array $data, int $clinicId, int $invitedById): Invite
    {
        $role    = Invite::JOB_TITLE_ROLES[$data['job_title']] ?? 'staff';
        $payload = [
            'name'          => $data['name'],
            'job_title'     => $data['job_title'],
            'role'          => $role,
            'status'        => 'pending',
            'expires_at'    => now()->addDays(7),
            'invited_by_id' => $invitedById,
            'token'         => Str::random(32),
            'short_token'   => Invite::generateShortToken(),
        ];

        $existing = Invite::where('clinic_id', $clinicId)->where('email', $data['email'])->first();

        if ($existing) {
            $oldStatus = $existing->status;
            $existing->update($payload);

            Log::info('[InviteService] Convite existente reutilizado com novos tokens', [
                'invite_id'       => $existing->id,
                'email'           => $existing->email,
                'old_status'      => $oldStatus,
                'new_short_token' => $existing->fresh()->short_token,
            ]);

            return $existing->fresh()->load('clinic', 'invitedBy');
        }

        $invite = Invite::create(array_merge($payload, [
            'clinic_id' => $clinicId,
            'email'     => $data['email'],
        ]));

        Log::info('[InviteService] Novo convite criado', [
            'invite_id'   => $invite->id,
            'short_token' => $invite->short_token,
            'email'       => $invite->email,
        ]);

        return $invite->load('clinic', 'invitedBy');
    }

    // ── Gerar novo token (mantém os dados, zera tokens e expiração) ────────
    public function regenerateToken(Invite $invite): Invite
    {
        $invite->update([
            'token'       => Str::random(32),
            'short_token' => Invite::generateShortToken(),
            'expires_at'  => now()->addDays(7),
            'status'      => 'pending',
        ]);

        $refreshed = $invite->fresh()->load('clinic', 'invitedBy');

        Log::info('[InviteService] Token de convite regenerado', [
            'invite_id'       => $invite->id,
            'new_short_token' => $refreshed->short_token,
            'email'           => $invite->email,
        ]);

        return $refreshed;
    }

    // ── Reativar convite expirado (novos tokens + nova expiração) ──────────
    public function reactivate(Invite $invite): Invite
    {
        $invite->update([
            'token'       => Str::random(32),
            'short_token' => Invite::generateShortToken(),
            'expires_at'  => now()->addDays(7),
            'status'      => 'pending',
        ]);

        $refreshed = $invite->fresh()->load('clinic', 'invitedBy');

        Log::info('[InviteService] Convite expirado reativado', [
            'invite_id'       => $invite->id,
            'new_short_token' => $refreshed->short_token,
            'email'           => $invite->email,
        ]);

        return $refreshed;
    }

    // ── Cancelar convite ───────────────────────────────────────────────────
    public function cancel(Invite $invite): void
    {
        $invite->update(['status' => 'cancelled']);

        Log::info('[InviteService] Convite cancelado', [
            'invite_id' => $invite->id,
            'email'     => $invite->email,
        ]);
    }

    // ── Enviar e-mail com logging completo ─────────────────────────────────
    public function dispatchEmail(Invite $invite): array
    {
        $mailerDriver = config('mail.default', 'log');
        $isLogDriver  = $mailerDriver === 'log';
        $link         = config('app.url') . '/convites/' . $invite->short_token;

        Log::info('[InviteService] Iniciando envio de e-mail de convite', [
            'invite_id'   => $invite->id,
            'email'       => $invite->email,
            'short_token' => $invite->short_token,
            'mailer'      => $mailerDriver,
            'from'        => config('mail.from.address'),
            'link'        => $link,
        ]);

        if ($isLogDriver) {
            Log::warning('[InviteService] MAIL_MAILER=log ativo — e-mail gravado no log, não entregue ao destinatário', [
                'invite_id'   => $invite->id,
                'email'       => $invite->email,
                'environment' => app()->environment(),
            ]);

            try {
                Mail::to($invite->email, $invite->name)->send(new TeamInviteMail($invite));
                Log::info('[InviteService] E-mail renderizado e gravado no log com sucesso', ['invite_id' => $invite->id]);
            } catch (\Throwable $e) {
                Log::error('[InviteService] Falha ao renderizar/gravar e-mail no log', [
                    'invite_id' => $invite->id,
                    'error'     => $e->getMessage(),
                ]);
            }

            return [
                'status'      => 'log_driver',
                'log_driver'  => true,
                'link'        => $link,
                'short_token' => $invite->short_token,
                'message'     => app()->isProduction()
                    ? 'MAIL_MAILER=log está ativo em produção. Configure um servidor SMTP real.'
                    : 'Ambiente de desenvolvimento detectado (MAIL_MAILER=log). O e-mail não foi enviado ao destinatário.',
            ];
        }

        try {
            Mail::to($invite->email, $invite->name)->send(new TeamInviteMail($invite));

            Log::info('[InviteService] E-mail de convite enviado com sucesso', [
                'invite_id'   => $invite->id,
                'email'       => $invite->email,
                'short_token' => $invite->short_token,
                'mailer'      => $mailerDriver,
            ]);

            AccessLog::record(
                action: AccessLog::ACTION_INVITE_SENT,
                description: "E-mail de convite enviado para {$invite->email}",
                metadata: [
                    'invite_id'   => $invite->id,
                    'short_token' => $invite->short_token,
                    'mailer'      => $mailerDriver,
                ],
            );

            return [
                'status'      => 'sent',
                'log_driver'  => false,
                'link'        => $link,
                'short_token' => $invite->short_token,
            ];

        } catch (\Throwable $e) {
            Log::error('[InviteService] Falha SMTP ao enviar e-mail de convite', [
                'invite_id'   => $invite->id,
                'email'       => $invite->email,
                'mailer'      => $mailerDriver,
                'smtp_host'   => config("mail.mailers.{$mailerDriver}.host"),
                'smtp_port'   => config("mail.mailers.{$mailerDriver}.port"),
                'error_class' => get_class($e),
                'error'       => $e->getMessage(),
            ]);

            return [
                'status'      => 'smtp_error',
                'log_driver'  => false,
                'link'        => $link,
                'short_token' => $invite->short_token,
                'error'       => app()->isProduction()
                    ? 'Falha na entrega do e-mail. Verifique as configurações SMTP do servidor.'
                    : $e->getMessage(),
            ];
        }
    }

    // ── Formatar convite para o frontend ───────────────────────────────────
    public static function formatInvite(Invite $invite): array
    {
        return [
            'id'          => $invite->id,
            'short_token' => $invite->short_token,
            'email'       => $invite->email,
            'name'        => $invite->name,
            'job_title'   => $invite->job_title,
            'status'      => $invite->status,
            'expires_at'  => $invite->expires_at?->toISOString(),
            'created_at'  => $invite->created_at?->toISOString(),
            'invited_by'  => $invite->invitedBy?->name ?? 'Administrador',
        ];
    }

    // ── Formatar usuário para o frontend ───────────────────────────────────
    private static function formatUser(User $user): array
    {
        return [
            'id'                 => $user->id,
            'name'               => $user->name,
            'email'              => $user->email,
            'job_title'          => $user->job_title,
            'profile_photo_path' => $user->profile_photo_path,
        ];
    }
}
