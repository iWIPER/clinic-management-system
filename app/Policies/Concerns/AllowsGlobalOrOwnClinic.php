<?php

namespace App\Policies\Concerns;

/**
 * Fase C4.1 — mecanismo único pra recursos com semântica GLOBAL OU PRÓPRIA
 * CLÍNICA (categorias/templates/perguntas padrão do sistema, com
 * clinic_id nulo, versus os personalizados de cada clínica).
 *
 * Deliberadamente o OPOSTO de AuthorizesClinicOwnership::sameClinic(): lá,
 * clinic_id nulo NUNCA autoriza (registro "morto"/órfão sem dono real,
 * como TaskLabel/PatientTag de sistema criados fora do fluxo normal).
 * Aqui, clinic_id nulo é o próprio SINAL de "registro padrão do sistema,
 * visível e editável por qualquer clínica" — comportamento que já existia
 * em Anamnesis{CategoryDefinition,Question,Template} e
 * Document{Category,Template} antes desta fase, preservado exatamente.
 */
trait AllowsGlobalOrOwnClinic
{
    protected function globalOrSameClinic(?int $modelClinicId): bool
    {
        if ($modelClinicId === null) {
            return true;
        }

        return $modelClinicId === (int) session('current_clinic_id');
    }
}
