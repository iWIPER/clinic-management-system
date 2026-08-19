<?php

namespace App\Policies;

use App\Models\ClinicalRecord;
use App\Models\User;
use App\Policies\Concerns\AuthorizesClinicOwnership;
use Illuminate\Auth\Access\Response;

/**
 * Fase C4 — substitui a checagem manual em
 * ClinicalRecordController::generatePdf(). ClinicalRecord tem clinic_id
 * próprio (não passa por Patient), então é resolvido direto contra a
 * clínica ativa da sessão. denyAsNotFound() preserva o 404 original
 * (abort_unless(..., 404)), não o 403 padrão do Laravel.
 */
class ClinicalRecordPolicy
{
    use AuthorizesClinicOwnership;

    public function view(User $user, ClinicalRecord $clinicalRecord): Response
    {
        return $this->sameClinic($clinicalRecord->clinic_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
