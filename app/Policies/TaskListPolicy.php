<?php

namespace App\Policies;

use App\Models\TaskList;
use App\Models\User;
use App\Policies\Concerns\AuthorizesClinicOwnership;
use Illuminate\Auth\Access\Response;

/**
 * Fase C4 — substitui TaskListController::authorizeOwnership(), que fazia
 * 3 checagens (preservadas aqui exatamente): tenant, "não é um escopo fixo
 * do sistema" (mine/team, identificados por key !== null) e "só quem criou
 * o escopo customizado pode administrá-lo" — não é role owner/admin, é
 * posse individual do registro. A mensagem original só existia pra essa
 * última condição (a mais comum de acontecer na prática); preservada aqui.
 */
class TaskListPolicy
{
    use AuthorizesClinicOwnership;

    public function update(User $user, TaskList $taskList): Response
    {
        return $this->manage($user, $taskList);
    }

    public function delete(User $user, TaskList $taskList): Response
    {
        return $this->manage($user, $taskList);
    }

    private function manage(User $user, TaskList $taskList): Response
    {
        if (!$this->sameClinic($taskList->clinic_id) || $taskList->key !== null) {
            return Response::deny();
        }

        return (int) $taskList->user_id === (int) $user->id
            ? Response::allow()
            : Response::deny('Apenas quem criou o escopo pode administrá-lo.');
    }
}
