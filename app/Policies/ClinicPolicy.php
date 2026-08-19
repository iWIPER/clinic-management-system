<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\User;

/**
 * Fase C4 — autorização de nível clínica (não de um recurso individual).
 * Substitui o padrão `in_array($user->roleInCurrentClinic(), ['owner', 'admin'])`
 * que estava duplicado em AgendaSettingsController, InviteController,
 * ProfileController e TeamController.
 *
 * Diferente das Policies de recurso (PatientPolicy etc.), aqui a regra tem
 * duas partes: a clínica alvo precisa ser de fato a clínica ativa da sessão
 * (a mesma garantia de tenant das outras Policies) E o papel do usuário
 * nela precisa ser owner ou admin (a regra de RBAC que já existia,
 * centralizada aqui em vez de reimplementada em cada controller).
 *
 * Não substitui o RBAC (Spatie continua instalado e intocado) — só
 * centraliza a decisão que já era tomada com base no papel real do
 * sistema, a coluna clinic_user.role (ver auditoria da C4: Spatie está
 * configurado para teams mas nenhum Role/Permission jamais foi atribuído
 * em nenhum lugar do código — não há permissionamento granular do Spatie
 * pra reaproveitar aqui).
 */
class ClinicPolicy
{
    public function manageTeam(User $user, ?Clinic $clinic): bool
    {
        if (!$clinic || (int) $clinic->id !== (int) session('current_clinic_id')) {
            return false;
        }

        return in_array($user->roleInCurrentClinic(), ['owner', 'admin'], true);
    }
}
