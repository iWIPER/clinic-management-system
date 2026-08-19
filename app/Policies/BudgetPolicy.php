<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;
use App\Policies\Concerns\AuthorizesClinicOwnership;

/**
 * Fase C4 — substitui a checagem manual em FinancingProposalController e
 * FinancingSimulationController (ambos operam sobre um Budget da clínica
 * ativa antes de simular/propor financiamento).
 */
class BudgetPolicy
{
    use AuthorizesClinicOwnership;

    public function view(User $user, Budget $budget): bool
    {
        return $this->sameClinic($budget->clinic_id);
    }
}
