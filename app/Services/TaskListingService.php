<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fonte única da consulta de tarefas para o painel de produtividade — usada
 * pela listagem (TaskController::index). Centralizar aqui permite que uma
 * futura visão Kanban (mesmo módulo, outra apresentação dos dados) reaproveite
 * exatamente a mesma consulta, sem duplicar a lógica de filtro.
 *
 * As 4 visões do painel (Entrada/Hoje/Próximas/Concluídas) são calculadas no
 * cliente a partir do payload devolvido aqui. Só dois eixos filtram no
 * servidor, porque são os únicos que mudam o conjunto de dados buscado:
 * "escopo" (mine/team/id numérico de um escopo personalizado) e a janela de
 * "Concluídas" (completed_days). Busca por texto e prioridade são filtradas
 * no cliente.
 */
class TaskListingService
{
    // Teto de segurança por LINHAS (não por data) — protege o payload/DOM
    // mesmo com "Sempre" selecionado (sem corte de dias) e mesmo que o
    // backlog de tarefas ativas cresça muito. Índice (clinic_id, status) já
    // existe, então o custo da consulta em si não é o problema; o risco é
    // volume de linhas trafegadas/renderizadas de uma vez (sem paginação
    // hoje). 500 é generoso pro uso real de uma clínica (produtividade de
    // equipe, não histórico de paciente) — na prática só age como rede de
    // segurança, não como corte funcional.
    private const MAX_ROWS = 500;

    public function filteredQuery(array $filters): Builder
    {
        $userId = (int) ($filters['user_id'] ?? 0);

        $query = Task::query()->with(['assignee:id,name', 'creator:id,name', 'patient:id,nome,sobrenome', 'labels']);

        $scope = $filters['scope'] ?? 'mine';

        if (is_numeric($scope)) {
            // Escopo personalizado — uma tarefa pertence a exatamente um
            // escopo por vez, então isto não passa pelos ramos mine/team.
            $query->where('task_list_id', (int) $scope);
        } elseif ($scope === 'team') {
            // Não é minha: atribuída a outra pessoa, ou sem responsável mas
            // criada por outra pessoa. Nunca inclui tarefas de um escopo
            // personalizado (task_list_id preenchido) — essas só aparecem
            // dentro do próprio escopo.
            $query->whereNull('task_list_id')->where(function (Builder $q) use ($userId) {
                $q->where(fn (Builder $q2) => $q2->whereNotNull('assigned_to')->where('assigned_to', '!=', $userId))
                    ->orWhere(fn (Builder $q2) => $q2->whereNull('assigned_to')->where('created_by', '!=', $userId));
            });
        } else {
            // Minha: atribuída a mim, ou sem responsável ainda e criada por mim.
            $query->whereNull('task_list_id')->where(function (Builder $q) use ($userId) {
                $q->where('assigned_to', $userId)
                    ->orWhere(fn (Builder $q2) => $q2->whereNull('assigned_to')->where('created_by', $userId));
            });
        }

        if ($search = $filters['search'] ?? null) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Teto de bom senso para o volume de uma clínica: tudo que ainda não
        // terminou, mais o que terminou dentro da janela pedida (o seletor de
        // período da visão "Concluídas" manda esse valor; nulo = sem corte,
        // ou seja "Sempre").
        // Atenção: não usar `??` aqui — quando o chamador manda null de
        // propósito (período "Sempre"), `??` trataria isso como "não veio" e
        // voltaria pro padrão de 30 dias, quebrando a opção "Sempre".
        $completedDays = array_key_exists('completed_days', $filters) ? $filters['completed_days'] : 30;
        $query->where(function (Builder $q) use ($completedDays) {
            $q->where('status', '!=', 'done');
            if ($completedDays !== null) {
                $q->orWhere('completed_at', '>=', now()->subDays((int) $completedDays));
            } else {
                $q->orWhere('status', 'done');
            }
        });

        // Concluídas ordenam por completed_at (não created_at) — a mais
        // recentemente concluída primeiro, prioridade nunca entra no
        // critério (ver TaskPanel.vue, que resssorta o bucket "done" com a
        // mesma regra no cliente). Não-concluídas caem no COALESCE e mantêm
        // o comportamento anterior (created_at DESC). id DESC só desempata
        // timestamps idênticos.
        return $query->orderByRaw('COALESCE(completed_at, created_at) DESC')->orderByDesc('id')->limit(self::MAX_ROWS);
    }
}
