<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'clinic_id', 'user_id', 'action', 'description',
        'ip_address', 'user_agent', 'device_type', 'browser', 'os',
        'city', 'country', 'metadata', 'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    // ── Ações disponíveis ──────────────────────────────────────────────────
    const ACTION_LOGIN               = 'login';
    const ACTION_LOGIN_FAILED        = 'login_failed';
    const ACTION_LOGOUT              = 'logout';
    const ACTION_PASSWORD_CHANGED    = 'password_changed';
    const ACTION_PATIENT_PAYMENTS_EXPORTED = 'patient_payments_exported';
    const ACTION_ACCESS_LOG_EXPORTED = 'access_log_exported';
    const ACTION_PROFILE_UPDATED     = 'profile_updated';
    const ACTION_INVITE_SENT         = 'invite_sent';
    const ACTION_INVITE_ACCEPTED     = 'invite_accepted';
    const ACTION_INVITE_CANCELLED    = 'invite_cancelled';
    const ACTION_INVITE_RESENT       = 'invite_resent';
    const ACTION_MEMBER_DEACTIVATED  = 'member_deactivated';
    const ACTION_MEMBER_REACTIVATED  = 'member_reactivated';
    const ACTION_DRIVE_CONNECTED     = 'google_drive_connected';
    const ACTION_DRIVE_DISCONNECTED  = 'google_drive_disconnected';
    const ACTION_PHOTO_UPLOADED      = 'photo_uploaded';
    const ACTION_PHOTO_REMOVED       = 'photo_removed';
    const ACTION_PATIENT_CREATED     = 'patient_created';
    const ACTION_PROCEDURE_CREATED   = 'procedure_created';
    const ACTION_REFERRAL_CLICK              = 'referral_click';
    const ACTION_REFERRAL_CONVERSION         = 'referral_conversion_registered';
    const ACTION_REFERRAL_TRIAL_STARTED      = 'referral_trial_started';
    const ACTION_REFERRAL_PLAN_SUBSCRIBED    = 'referral_plan_subscribed';
    const ACTION_REFERRAL_BONUS_RELEASED     = 'referral_bonus_released';
    const ACTION_REFERRAL_BONUS_ELIGIBLE     = 'referral_bonus_eligible';
    const ACTION_REFERRAL_PAYMENT_SENT       = 'referral_payment_sent';
    const ACTION_REFERRAL_PIX_UPDATED        = 'referral_pix_updated';
    const ACTION_REFERRAL_WITHDRAWAL         = 'referral_withdrawal_requested';
    const ACTION_SUBSCRIPTION_TRIAL          = 'subscription_trial_started';
    const ACTION_SUBSCRIPTION_ACTIVATED      = 'subscription_activated';
    const ACTION_ADMIN_SETTINGS              = 'admin_settings_updated';
    const ACTION_ADMIN_PAYMENT_APPROVED      = 'admin_payment_approved';
    const ACTION_ADMIN_PAYMENT_REJECTED      = 'admin_payment_rejected';
    const ACTION_ADMIN_PLAN_UPDATED          = 'admin_plan_updated';
    const ACTION_ADMIN_CLINIC_BLOCKED        = 'admin_clinic_blocked';
    const ACTION_ADMIN_CLINIC_UNBLOCKED      = 'admin_clinic_unblocked';
    const ACTION_ADMIN_CLINIC_CONTEXT_ENTERED = 'admin_clinic_context_entered';
    const ACTION_ADMIN_CLINIC_CONTEXT_EXITED  = 'admin_clinic_context_exited';
    const ACTION_SYSTEM_ADMIN_GRANTED        = 'system_admin_granted';
    const ACTION_SYSTEM_ADMIN_REVOKED        = 'system_admin_revoked';
    const ACTION_ADMIN_USER_BLOCKED          = 'admin_user_blocked';
    const ACTION_ADMIN_USER_UNBLOCKED        = 'admin_user_unblocked';
    const ACTION_ADMIN_USER_ANONYMIZED       = 'admin_user_anonymized';
    const ACTION_ADMIN_USER_DELETED          = 'admin_user_deleted';
    const ACTION_ADMIN_EXPORT_DOWNLOADED     = 'admin_export_downloaded';

    // Labels em PT-BR
    const LABELS = [
        'login'               => 'Login realizado',
        'login_failed'        => 'Tentativa de login falhou',
        'logout'              => 'Logout',
        'password_changed'    => 'Senha alterada',
        'patient_payments_exported' => 'Pagamentos de paciente exportados',
        'access_log_exported'       => 'Logs de acesso exportados',
        'profile_updated'     => 'Perfil atualizado',
        'invite_sent'         => 'Convite enviado',
        'invite_accepted'     => 'Convite aceito',
        'invite_cancelled'    => 'Convite cancelado',
        'invite_resent'       => 'Convite reenviado',
        'member_deactivated'  => 'Membro desativado',
        'member_reactivated'  => 'Membro reativado',
        'google_drive_connected'    => 'Google Drive conectado',
        'google_drive_disconnected' => 'Google Drive desconectado',
        'photo_uploaded'      => 'Upload realizado',
        'photo_removed'       => 'Arquivo removido',
        'patient_created'     => 'Paciente criado',
        'procedure_created'   => 'Procedimento criado',
        'referral_click'                    => 'Clique no link de indicação',
        'referral_conversion_registered'    => 'Cadastro via indicação',
        'referral_trial_started'            => 'Nova indicação em teste',
        'referral_plan_subscribed'          => 'Indicação assinou plano',
        'referral_bonus_released'           => 'Bônus liberado',
        'referral_bonus_eligible'           => 'Bônus elegível',
        'referral_payment_sent'             => 'Pagamento PIX enviado',
        'referral_pix_updated'              => 'PIX cadastrado',
        'referral_withdrawal_requested'     => 'Saque solicitado',
        'subscription_trial_started'        => 'Trial iniciado',
        'subscription_activated'            => 'Assinatura ativada',
        'admin_settings_updated'            => 'Config. indicações alterada',
        'admin_payment_approved'            => 'Pagamento aprovado',
        'admin_payment_rejected'            => 'Pagamento recusado',
        'admin_plan_updated'                => 'Plano atualizado',
        'admin_clinic_blocked'              => 'Clínica bloqueada',
        'admin_clinic_unblocked'            => 'Clínica desbloqueada',
        'admin_clinic_context_entered'      => 'Administrador entrou na clínica',
        'admin_clinic_context_exited'       => 'Administrador voltou ao Backoffice',
        'system_admin_granted'              => 'System Admin concedido',
        'system_admin_revoked'              => 'System Admin removido',
        'admin_user_blocked'                => 'Usuário bloqueado',
        'admin_user_unblocked'              => 'Usuário desbloqueado',
        'admin_user_anonymized'             => 'Conta de usuário anonimizada',
        'admin_user_deleted'                => 'Conta de usuário excluída',
        'admin_export_downloaded'           => 'Exportação administrativa',
    ];

    // ── Relações ───────────────────────────────────────────────────────────
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    // ── Label legível ──────────────────────────────────────────────────────
    public function getActionLabelAttribute(): string
    {
        return self::LABELS[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));
    }

    // ── Helper estático de gravação ────────────────────────────────────────
    public static function record(
        string  $action,
        string  $description = '',
        ?array  $metadata    = null,
        ?int    $userId      = null,
        ?int    $clinicId    = null,
    ): self {
        $request = request();
        $ua      = $request?->userAgent() ?? '';
        $parsed  = self::parseUserAgent($ua);

        return self::create([
            'clinic_id'   => $clinicId ?? session('current_clinic_id'),
            'user_id'     => $userId   ?? auth()->id(),
            'action'      => $action,
            'description' => $description ?: (self::LABELS[$action] ?? $action),
            'ip_address'  => $request?->ip(),
            'user_agent'  => $ua,
            'device_type' => $parsed['device'],
            'browser'     => $parsed['browser'],
            'os'          => $parsed['os'],
            'metadata'    => $metadata,
            'created_at'  => now(),
        ]);
    }

    // ── Parser de User-Agent ───────────────────────────────────────────────
    public static function parseUserAgent(string $ua): array
    {
        // Dispositivo
        $device = 'desktop';
        if (preg_match('/tablet|ipad/i', $ua)) {
            $device = 'tablet';
        } elseif (preg_match('/mobile|android|iphone|ipod/i', $ua)) {
            $device = 'mobile';
        } elseif (preg_match('/macbook|laptop/i', $ua)) {
            $device = 'notebook';
        }

        // Navegador (ordem importa: Edge > Opera > Chrome > Firefox > Safari)
        $browser = null;
        if (preg_match('/Edg\/(\d+)/i', $ua, $m))         $browser = 'Edge ' . $m[1];
        elseif (preg_match('/OPR\/(\d+)/i', $ua, $m))     $browser = 'Opera ' . $m[1];
        elseif (preg_match('/Chrome\/(\d+)/i', $ua, $m))  $browser = 'Chrome ' . $m[1];
        elseif (preg_match('/Firefox\/(\d+)/i', $ua, $m)) $browser = 'Firefox ' . $m[1];
        elseif (preg_match('/Version\/[\d.]+ Safari/i', $ua, $m)) $browser = 'Safari';

        // Sistema operacional
        $os = null;
        if (preg_match('/Windows NT ([\d.]+)/i', $ua, $m)) {
            $winMap = ['10.0' => 'Windows 10/11', '6.3' => 'Windows 8.1', '6.1' => 'Windows 7'];
            $os = $winMap[$m[1]] ?? 'Windows';
        } elseif (preg_match('/Mac OS X ([\d_]+)/i', $ua, $m)) {
            $os = 'macOS ' . str_replace('_', '.', $m[1]);
        } elseif (preg_match('/Android ([\d.]+)/i', $ua, $m)) {
            $os = 'Android ' . $m[1];
            if ($device === 'desktop') $device = 'mobile';
        } elseif (preg_match('/iPhone OS ([\d_]+)/i', $ua, $m)) {
            $os = 'iOS ' . str_replace('_', '.', $m[1]);
            if ($device === 'desktop') $device = 'mobile';
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
        }

        return compact('device', 'browser', 'os');
    }
}
