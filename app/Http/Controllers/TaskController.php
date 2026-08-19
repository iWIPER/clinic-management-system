<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Task;
use App\Models\TaskLabel;
use App\Models\TaskList;
use App\Services\TaskListingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    /**
     * Painel de Tarefas é um overlay client-side (não uma página Inertia) —
     * busca tudo que precisa de uma vez em JSON; as 4 visões (Entrada/Hoje/
     * Próximas/Concluídas) são calculadas no frontend a partir desta lista.
     */
    public function index(Request $request, TaskListingService $listingService)
    {
        $clinicId = session('current_clinic_id');
        $clinic = Clinic::findOrFail($clinicId);

        $days = $request->input('days', 30);
        $scope = $request->input('scope', 'mine');

        if (is_numeric($scope)) {
            $customList = TaskList::where('clinic_id', $clinicId)->whereNull('key')->find((int) $scope);
            abort_if(!$customList, 404);
            abort_unless($customList->isAccessibleBy(auth()->id()), 403, 'Você não tem acesso a este escopo.');
        }

        $tasks = $listingService->filteredQuery([
            'scope'          => $scope,
            'completed_days' => $days === 'all' ? null : (int) $days,
            'user_id'        => auth()->id(),
        ])->get();

        $customLists = TaskList::where('clinic_id', $clinicId)
            ->whereNull('key')
            ->accessibleBy(auth()->id())
            ->orderBy('id')
            ->get()
            ->map(fn (TaskList $list) => $list->present(auth()->id()))
            ->values();

        return response()->json([
            'tasks'          => $tasks,
            'statuses'       => Task::STATUSES,
            'kanbanStatuses' => Task::KANBAN_STATUSES,
            'priorities'     => Task::PRIORITIES,
            'availableLabels' => TaskLabel::forClinic($clinicId)->orderBy('name')->get(['id', 'name', 'color']),
            'teamMembers'    => $clinic->users()->select('users.id', 'users.name')->orderBy('users.name')->get(),
            'currentUserId'  => auth()->id(),
            'lists'          => [
                'mine'   => $this->findOrCreateList($clinicId, 'mine')->present(auth()->id()),
                'team'   => $this->findOrCreateList($clinicId, 'team')->present(auth()->id()),
                'custom' => $customLists,
            ],
        ]);
    }

    /**
     * Painel "Controle" — resumo do que foi efetivamente concluído hoje
     * (completed_at, não due_date), separado por escopo. Consulta única
     * (índice clinic_id+status+completed_at cobre o filtro) e agrupamento em
     * PHP — evita buscar tudo da clínica e filtrar no cliente.
     */
    public function controlPanel()
    {
        $clinicId = session('current_clinic_id');
        $userId = auth()->id();

        $customLists = TaskList::where('clinic_id', $clinicId)
            ->whereNull('key')
            ->accessibleBy($userId)
            ->orderBy('id')
            ->get(['id', 'name', 'color']);

        $completedToday = Task::where('status', 'done')
            ->whereBetween('completed_at', [now()->startOfDay(), now()->endOfDay()])
            ->where(function ($q) use ($customLists) {
                $q->whereNull('task_list_id')->orWhereIn('task_list_id', $customLists->pluck('id'));
            })
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'task_list_id', 'assigned_to', 'created_by']);

        $isMine = fn (Task $t) => $t->assigned_to === $userId || ($t->assigned_to === null && $t->created_by === $userId);
        $present = fn ($group) => $group->map(fn (Task $t) => ['id' => $t->id, 'title' => $t->title])->values();

        $mine = $completedToday->filter(fn (Task $t) => $t->task_list_id === null && $isMine($t));
        $team = $completedToday->filter(fn (Task $t) => $t->task_list_id === null && ! $isMine($t));

        $mineList = $this->findOrCreateList($clinicId, 'mine');
        $teamList = $this->findOrCreateList($clinicId, 'team');

        $sections = [
            ['key' => 'mine', 'name' => $mineList->name, 'color' => $mineList->color, 'count' => $mine->count(), 'tasks' => $present($mine)],
            ['key' => 'team', 'name' => $teamList->name, 'color' => $teamList->color, 'count' => $team->count(), 'tasks' => $present($team)],
        ];

        foreach ($customLists as $list) {
            $listTasks = $completedToday->filter(fn (Task $t) => $t->task_list_id === $list->id);
            if ($listTasks->isEmpty()) continue;

            $sections[] = [
                'key'   => (string) $list->id,
                'name'  => $list->name,
                'color' => $list->color,
                'count' => $listTasks->count(),
                'tasks' => $present($listTasks),
            ];
        }

        return response()->json(['sections' => $sections]);
    }

    public function store(Request $request)
    {
        [$validated, $labelIds] = $this->validatedTask($request);

        // Urgente sem vencimento não pode ficar esquecida em "Entrada" — cai
        // em "Hoje" automaticamente. Só na criação: mudar a prioridade de uma
        // tarefa já existente para urgente depois não deve empurrar a data
        // (ver update(), que não passa por aqui).
        if ($validated['priority'] === 'urgente' && empty($validated['due_date'])) {
            $validated['due_date'] = now()->toDateString();
        }

        $task = Task::create($validated + ['created_by' => auth()->id()]);
        $task->labels()->sync($labelIds);

        return response()->json($this->presentTask($task));
    }

    public function update(Request $request, Task $task)
    {
        [$validated, $labelIds] = $this->validatedTask($request);

        $task->update($validated);
        $task->labels()->sync($labelIds);

        return response()->json($this->presentTask($task));
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return response()->json(['id' => $task->id]);
    }

    public function updateStatus(Request $request, Task $task)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
        ]);

        $task->update([
            'status'       => $validated['status'],
            'completed_at' => $validated['status'] === 'done' ? now() : null,
        ]);

        return response()->json($this->presentTask($task));
    }

    public function togglePin(Task $task)
    {
        $task->update(['pinned_at' => $task->pinned_at ? null : now()]);

        return response()->json($this->presentTask($task));
    }

    public function toggleFavorite(Task $task)
    {
        $task->update(['is_favorite' => ! $task->is_favorite]);

        return response()->json($this->presentTask($task));
    }

    // Regra compartilhada entre a validação de criação/edição (validatedTask)
    // e o move() do Board — só a condição é reusada; cada chamador decide seu
    // próprio campo/mensagem de erro, porque os dois endpoints têm formatos
    // de payload diferentes.
    private static function isUrgentWithFutureDueDate(string $priority, ?string $dueDate): bool
    {
        return $priority === 'urgente' && $dueDate && $dueDate > now()->toDateString();
    }

    private function findOrCreateList(int $clinicId, string $key): TaskList
    {
        // Cor/nome default seguem o exemplo já combinado: azul pra "Minhas
        // tarefas", vermelho pra "Tarefas da equipe" — o usuário troca depois
        // pela engrenagem, isso só evita a lista nascer sem cor nenhuma.
        $defaults = $key === 'mine'
            ? ['name' => 'Minhas tarefas', 'color' => '#3b82f6', 'sharing_type' => TaskList::SHARING_PRIVATE]
            : ['name' => 'Tarefas da equipe', 'color' => '#ef4444', 'sharing_type' => TaskList::SHARING_TEAM];

        return TaskList::firstOrCreate(
            ['clinic_id' => $clinicId, 'user_id' => auth()->id(), 'key' => $key],
            $defaults
        );
    }

    private function presentTask(Task $task): Task
    {
        return $task->load(['assignee:id,name', 'creator:id,name', 'patient:id,nome,sobrenome', 'labels']);
    }

    private function validatedTask(Request $request): array
    {
        $clinicId = session('current_clinic_id');

        $validated = $request->validate([
            'title'        => 'required|string|max:40',
            'description'  => 'required|string|max:3000',
            'status'       => ['required', Rule::in(array_keys(Task::STATUSES))],
            // "Urgente" contradiz um vencimento futuro (cai em "Próximas" no
            // painel — ver bucketOf em TaskPanel.vue): se é urgente, é pra
            // hoje. Mesmo corte de data (devido depois de hoje = "Próximas").
            'priority'     => [
                'required', Rule::in(array_keys(Task::PRIORITIES)),
                function ($attribute, $value, $fail) use ($request) {
                    if (self::isUrgentWithFutureDueDate($value, $request->input('due_date'))) {
                        $fail('Prioridade urgente não está disponível para tarefas em "Próximas" (vencimento futuro).');
                    }
                },
            ],
            'assigned_to'  => ['nullable', Rule::exists('clinic_user', 'user_id')->where('clinic_id', $clinicId)],
            'patient_id'   => ['nullable', Rule::exists('patients', 'id')->where('clinic_id', $clinicId)],
            'task_list_id' => ['nullable', Rule::exists('task_lists', 'id')->where('clinic_id', $clinicId)->whereNull('key')],
            'due_date'     => 'nullable|date',
            'label_ids'    => 'array|max:2',
            'label_ids.*'  => Rule::exists('task_labels', 'id')->where(
                fn ($query) => $query->whereNull('clinic_id')->orWhere('clinic_id', $clinicId)
            ),
        ], [
            'label_ids.max' => 'Uma tarefa pode possuir no máximo 2 etiquetas.',
        ]);

        // Rule::exists só confirma que o escopo existe nesta clínica — não
        // que o usuário atual tem permissão de acesso (ex.: escopo "private"
        // de outra pessoa). Checado à parte pra devolver o erro no mesmo
        // formato 422 dos demais campos.
        if (! empty($validated['task_list_id'])) {
            $list = TaskList::find($validated['task_list_id']);
            if (! $list->isAccessibleBy(auth()->id())) {
                throw ValidationException::withMessages(['task_list_id' => 'Você não tem acesso a este escopo.']);
            }
        }

        $labelIds = $validated['label_ids'] ?? [];
        unset($validated['label_ids']);

        return [$validated, $labelIds];
    }
}
