<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * "Escopos" cobrem dois tipos de registro na tabela `task_lists`:
 *
 * - Fixos ("mine"/"team"): 1 linha por usuário por clínica, é só a
 *   preferência pessoal de nome/cor sobre um bucket calculado (ver
 *   TaskListingService). Nome e compartilhamento são fixos por regra de
 *   negócio — só a cor pode mudar (ver update()).
 * - Personalizados (key nulo): uma entidade real e compartilhada da
 *   clínica inteira (ex.: "Financeiro"), não por usuário. `user_id` aqui é
 *   "quem criou e administra" — só essa pessoa edita/exclui. Compartilhar
 *   vira controle de acesso de verdade (quem enxerga o escopo existir),
 *   não só um filtro de visualização como nos fixos.
 */
class TaskListController extends Controller
{
    private const MAX_CUSTOM_LISTS = 5;

    public function update(Request $request, string $key)
    {
        abort_unless(in_array($key, ['mine', 'team'], true), 404);

        $clinicId = session('current_clinic_id');

        // Nome e compartilhamento dos escopos fixos ficam travados por
        // regra de negócio — só a cor é editável aqui.
        $validated = $request->validate([
            'color' => 'required|string|max:20',
        ]);

        $defaults = $key === 'mine'
            ? ['name' => 'Minhas tarefas', 'sharing_type' => TaskList::SHARING_PRIVATE]
            : ['name' => 'Tarefas da equipe', 'sharing_type' => TaskList::SHARING_TEAM];

        $list = TaskList::updateOrCreate(
            ['clinic_id' => $clinicId, 'user_id' => auth()->id(), 'key' => $key],
            $defaults + ['color' => $validated['color']]
        );

        return response()->json($list->present(auth()->id()));
    }

    public function store(Request $request)
    {
        $clinicId = session('current_clinic_id');
        $validated = $this->validatedCustomList($request, $clinicId);

        if (TaskList::where('clinic_id', $clinicId)->whereNull('key')->count() >= self::MAX_CUSTOM_LISTS) {
            return response()->json([
                'message' => 'Limite de escopos personalizados atingido. Exclua um escopo existente para criar outro.',
            ], 409);
        }

        $list = TaskList::create([
            'clinic_id'    => $clinicId,
            'user_id'      => auth()->id(),
            'key'          => null,
            'name'         => $validated['name'],
            'color'        => $validated['color'],
            'sharing_type' => $validated['sharing_type'],
        ]);

        $this->syncSharing($list, $validated);

        return response()->json($list->present(auth()->id()), 201);
    }

    public function updateCustom(Request $request, TaskList $taskList)
    {
        $this->authorize('update', $taskList);

        $clinicId = session('current_clinic_id');
        $validated = $this->validatedCustomList($request, $clinicId);

        $taskList->update([
            'name'         => $validated['name'],
            'color'        => $validated['color'],
            'sharing_type' => $validated['sharing_type'],
        ]);

        $this->syncSharing($taskList, $validated);

        return response()->json($taskList->present(auth()->id()));
    }

    public function destroy(TaskList $taskList)
    {
        $this->authorize('delete', $taskList);

        DB::transaction(function () use ($taskList) {
            // Nada se perde — as tarefas só saem do escopo excluído e voltam
            // a cair no cálculo padrão mine/team (ver TaskListingService).
            Task::where('task_list_id', $taskList->id)->update(['task_list_id' => null]);
            $taskList->delete(); // cascade cuida de task_list_shares
        });

        return response()->json(['id' => $taskList->id]);
    }

    private function validatedCustomList(Request $request, int $clinicId): array
    {
        return $request->validate([
            'name'              => 'required|string|max:30',
            'color'             => 'required|string|max:20',
            'sharing_type'      => ['required', Rule::in([
                TaskList::SHARING_PRIVATE, TaskList::SHARING_TEAM, TaskList::SHARING_SELECTED,
            ])],
            'shared_user_ids'   => 'array',
            'shared_user_ids.*' => Rule::exists('clinic_user', 'user_id')->where('clinic_id', $clinicId),
        ]);
    }

    private function syncSharing(TaskList $list, array $validated): void
    {
        $list->sharedWith()->sync(
            $validated['sharing_type'] === TaskList::SHARING_SELECTED ? ($validated['shared_user_ids'] ?? []) : []
        );
    }
}
