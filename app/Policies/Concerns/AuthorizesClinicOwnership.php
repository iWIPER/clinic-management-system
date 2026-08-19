<?php

namespace App\Policies\Concerns;

/**
 * Fase C4 — mecanismo único de "este recurso pertence à clínica ativa da
 * sessão?", reutilizado por toda Policy que só precisa de isolamento de
 * tenant (sem regra de papel adicional). Substitui o padrão
 * `abort_unless((int) $model->clinic_id === (int) session('current_clinic_id'), ...)`
 * que estava duplicado em ~12 controllers.
 *
 * Cast pra int preserva o comportamento já existente nesses controllers:
 * um clinic_id nulo (registros "globais", ex.: TaskLabel/PatientTag de
 * sistema) vira 0 e nunca bate com um session id real — bloqueando
 * corretamente edição/exclusão desses registros por qualquer clínica.
 */
trait AuthorizesClinicOwnership
{
    protected function sameClinic(?int $modelClinicId): bool
    {
        return (int) $modelClinicId === (int) session('current_clinic_id');
    }
}
