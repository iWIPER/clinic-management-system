<?php

namespace App\Policies;

use App\Models\Chair;
use App\Models\User;
use App\Policies\Concerns\AuthorizesClinicOwnership;

/**
 * Fase C4 — substitui ChairController::authorizeOwnership().
 */
class ChairPolicy
{
    use AuthorizesClinicOwnership;

    public function update(User $user, Chair $chair): bool
    {
        return $this->sameClinic($chair->clinic_id);
    }

    public function delete(User $user, Chair $chair): bool
    {
        return $this->sameClinic($chair->clinic_id);
    }
}
