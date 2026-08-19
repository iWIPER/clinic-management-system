<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use App\Policies\Concerns\AuthorizesClinicOwnership;
use Illuminate\Auth\Access\Response;

/**
 * Fase C4 — autorização centralizada pro recurso mais sensível do sistema
 * (dado de saúde do paciente). Reúne, num único lugar testável, a regra que
 * antes era reimplementada como `abort_unless($patient->clinic_id === ...)`
 * em PatientProntuarioController, PatientOdontogramController,
 * PatientAnamnesisController, PatientDocumentController e
 * DocumentShareController — e passa a valer também em PatientController,
 * que antes confiava só no ClinicScope (sem checagem explícita nenhuma).
 *
 * ClinicScope continua ativo e é a primeira linha de defesa (reduz o que
 * chega até aqui); esta Policy é a segunda camada, explícita e testável,
 * que não depende do Scope estar ativo (ex.: contexto de console/job).
 *
 * denyAsNotFound() preserva o comportamento original: todo abort_unless que
 * esta Policy substitui usava 404 (não 403) — o padrão deliberado deste
 * projeto pra violação de tenant é "parece que não existe", não "existe mas
 * você não pode ver".
 */
class PatientPolicy
{
    use AuthorizesClinicOwnership;

    public function view(User $user, Patient $patient): Response
    {
        return $this->decide($patient);
    }

    public function update(User $user, Patient $patient): Response
    {
        return $this->decide($patient);
    }

    public function delete(User $user, Patient $patient): Response
    {
        return $this->decide($patient);
    }

    private function decide(Patient $patient): Response
    {
        return $this->sameClinic($patient->clinic_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
