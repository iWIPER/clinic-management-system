<?php

namespace App\Services;

use App\Models\AccessLog;
use App\Models\SystemAdmin;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Invariantes de negócio do privilégio de System Admin — deliberadamente
 * fora de uma Policy (Policy decide "pode ou não pode fazer X", estas são
 * regras de integridade do próprio domínio: nunca deixar a plataforma sem
 * nenhum administrador).
 */
class SystemAdminService
{
    /**
     * $grantedBy nulo representa uma concessão via bootstrap (comando
     * Artisan rodado fora do navegador, sem um admin logado concedendo).
     */
    public function grant(User $target, ?User $grantedBy): SystemAdmin
    {
        if ($target->isSystemAdmin()) {
            throw ValidationException::withMessages(['user' => 'Este usuário já é System Admin.']);
        }

        $grant = SystemAdmin::create([
            'user_id'       => $target->id,
            'granted_by_id' => $grantedBy?->id,
            'granted_at'    => now(),
        ]);

        AccessLog::record(
            action: 'system_admin_granted',
            description: $grantedBy
                ? "{$target->name} recebeu privilégio de System Admin, concedido por {$grantedBy->name}"
                : "{$target->name} recebeu privilégio de System Admin via bootstrap (linha de comando)",
            metadata: ['target_user_id' => $target->id, 'granted_by_id' => $grantedBy?->id],
            userId: $grantedBy?->id,
        );

        return $grant;
    }

    /**
     * @throws ValidationException quando remover deixaria a plataforma sem nenhum System Admin.
     */
    public function revoke(User $target, User $revokedBy): void
    {
        $grant = $target->systemAdminGrant()->first();

        if (! $grant) {
            throw ValidationException::withMessages(['user' => 'Este usuário não é System Admin.']);
        }

        if (SystemAdmin::active()->count() <= 1) {
            throw ValidationException::withMessages(['user' => 'Não é possível remover o último System Admin da plataforma.']);
        }

        $grant->update(['revoked_by_id' => $revokedBy->id, 'revoked_at' => now()]);

        AccessLog::record(
            action: 'system_admin_revoked',
            description: "Privilégio de System Admin de {$target->name} removido por {$revokedBy->name}",
            metadata: ['target_user_id' => $target->id, 'revoked_by_id' => $revokedBy->id],
            userId: $revokedBy->id,
        );
    }
}
