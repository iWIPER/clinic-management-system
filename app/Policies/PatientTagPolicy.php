<?php

namespace App\Policies;

use App\Models\PatientTag;
use App\Models\User;
use App\Policies\Concerns\AuthorizesClinicOwnership;
use Illuminate\Auth\Access\Response;

/**
 * Fase C4 — substitui PatientMarkerController::authorizeClinicOwnership().
 * clinic_id nulo (marcador global de sistema) nunca autoriza — mesmo
 * comportamento de antes, preservado pelo cast em sameClinic(). Mensagem
 * de negação preservada (era customizada em PT-BR no abort_unless original).
 */
class PatientTagPolicy
{
    use AuthorizesClinicOwnership;

    public function update(User $user, PatientTag $marker): Response
    {
        return $this->decide($marker);
    }

    public function delete(User $user, PatientTag $marker): Response
    {
        return $this->decide($marker);
    }

    private function decide(PatientTag $marker): Response
    {
        return $this->sameClinic($marker->clinic_id)
            ? Response::allow()
            : Response::deny('Este marcador não pode ser administrado por esta clínica.');
    }
}
