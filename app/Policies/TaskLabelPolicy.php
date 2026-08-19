<?php

namespace App\Policies;

use App\Models\TaskLabel;
use App\Models\User;
use App\Policies\Concerns\AuthorizesClinicOwnership;
use Illuminate\Auth\Access\Response;

/**
 * Fase C4 — substitui a checagem manual em TaskLabelController::destroy().
 * clinic_id nulo (etiqueta global de sistema) nunca autoriza — mesmo
 * comportamento de antes, preservado pelo cast em sameClinic(). Mensagem
 * de negação preservada (era customizada em PT-BR no abort_unless original).
 */
class TaskLabelPolicy
{
    use AuthorizesClinicOwnership;

    public function delete(User $user, TaskLabel $label): Response
    {
        return $this->sameClinic($label->clinic_id)
            ? Response::allow()
            : Response::deny('Esta etiqueta não pode ser administrada por esta clínica.');
    }
}
