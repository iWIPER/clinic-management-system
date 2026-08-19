<?php

namespace App\Policies;

use App\Models\Invite;
use App\Models\User;
use App\Policies\Concerns\AuthorizesClinicOwnership;

/**
 * Fase C4 — achado do Passo 11 (auditoria final): InviteController tinha 4
 * checagens de tenant (resend/regenerateToken/reactivate/destroy) fora do
 * levantamento original — usavam `==` em vez de `===`, por isso escaparam
 * do grep inicial. Invite não usa BelongsToClinic/ClinicScope, então esta
 * é a única proteção contra um owner/admin de uma clínica manipular o
 * convite de outra.
 */
class InvitePolicy
{
    use AuthorizesClinicOwnership;

    public function manage(User $user, Invite $invite): bool
    {
        return $this->sameClinic($invite->clinic_id);
    }
}
