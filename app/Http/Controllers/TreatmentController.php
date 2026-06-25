<?php

namespace App\Http\Controllers;

use App\Data\DentalTreatmentCatalog;
use App\Models\Treatment;
use App\Models\TreatmentAuditLog;
use App\Services\TreatmentCatalogService;
use App\Services\TreatmentStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TreatmentController extends Controller
{
    public function index(Request $request, TreatmentCatalogService $catalogService)
    {
        $catalogService->ensureCatalogForCurrentClinic(auth()->id());

        $search = $request->input('search');
        $categoriaFilter = $request->input('categoria');

        $activeQuery = Treatment::query()
            ->active()
            ->with('parent:id,nome,categoria')
            ->orderBy('categoria')
            ->orderBy('ordem')
            ->orderBy('nome');

        if ($search) {
            $activeQuery->where('nome', 'like', "%{$search}%");
        }
        if ($categoriaFilter) {
            $activeQuery->where('categoria', $categoriaFilter);
        }

        $activeTreatments = $activeQuery->get();
        $groupedActive = $this->groupByCategory($activeTreatments);

        $inactiveQuery = Treatment::query()
            ->where('ativo', false)
            ->with(['deactivatedBy:id,name', 'parent:id,nome'])
            ->orderByDesc('deactivated_at');

        if ($search) {
            $inactiveQuery->where('nome', 'like', "%{$search}%");
        }

        $inactiveTreatments = $inactiveQuery->get();

        return Inertia::render('Treatments/Index', [
            'groupedTreatments' => $groupedActive,
            'inactiveTreatments' => $inactiveTreatments,
            'categories' => array_keys(DentalTreatmentCatalog::categories()),
            'filters' => $request->only(['search', 'categoria']),
            'catalogCount' => Treatment::count(),
        ]);
    }

    public function show(Treatment $treatment, TreatmentStatsService $statsService)
    {
        $treatment->load(['deactivatedBy:id,name', 'parent:id,nome,categoria', 'auditLogs.user:id,name']);

        $breadcrumb = [
            ['label' => 'Catálogo', 'href' => route('treatments.index')],
            ['label' => $treatment->categoria ?? 'Geral', 'href' => route('treatments.index', ['categoria' => $treatment->categoria])],
        ];

        if ($treatment->parent) {
            $breadcrumb[] = ['label' => $treatment->parent->nome, 'href' => route('treatments.show', $treatment->parent->id)];
        }

        $breadcrumb[] = ['label' => $treatment->nome, 'href' => null];

        return Inertia::render('Treatments/Show', [
            'treatment' => $treatment,
            'stats' => $statsService->forTreatment($treatment),
            'hasLinkedAttendances' => $statsService->hasLinkedAttendances($treatment),
            'breadcrumb' => $breadcrumb,
            'auditLogs' => $treatment->auditLogs->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'action_label' => TreatmentAuditLog::ACTIONS[$log->action] ?? $log->action,
                'user_name' => $log->user?->name,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at,
                'summary' => $this->formatAuditSummary($log),
            ]),
        ]);
    }

    public function create()
    {
        $parents = Treatment::where('tipo', 'grupo')->orderBy('nome')->get(['id', 'nome', 'categoria']);

        return Inertia::render('Treatments/Create', [
            'categories' => array_keys(DentalTreatmentCatalog::categories()),
            'parents' => $parents,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'categoria' => 'nullable|string|max:100',
            'tipo' => 'nullable|string|in:procedimento,variacao,grupo',
            'parent_id' => 'nullable|exists:treatments,id',
            'especialidade' => 'nullable|string|max:100',
            'duracao_padrao' => 'nullable|integer|min:0',
            'preco_base' => 'nullable|numeric|min:0',
            'descricao' => 'nullable|string',
            'cor' => 'nullable|string|max:7',
            'ordem' => 'nullable|integer|min:0',
        ]);

        $categories = DentalTreatmentCatalog::categories();
        $cor = $validated['cor']
            ?? ($categories[$validated['categoria'] ?? '']['cor'] ?? '#10b981');

        $treatment = Treatment::create(array_merge($validated, [
            'ativo' => true,
            'tipo' => $validated['tipo'] ?? 'procedimento',
            'cor' => $cor,
        ]));

        $this->logAudit($treatment, 'created', ['nome' => $treatment->nome]);

        return redirect()
            ->route('treatments.show', $treatment)
            ->with('success', 'Procedimento cadastrado com sucesso!');
    }

    public function edit(Treatment $treatment)
    {
        $parents = Treatment::where('tipo', 'grupo')->where('id', '!=', $treatment->id)->orderBy('nome')->get(['id', 'nome', 'categoria']);

        return Inertia::render('Treatments/Edit', [
            'treatment' => $treatment,
            'categories' => array_keys(DentalTreatmentCatalog::categories()),
            'parents' => $parents,
        ]);
    }

    public function update(Request $request, Treatment $treatment)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'categoria' => 'nullable|string|max:100',
            'tipo' => 'nullable|string|in:procedimento,variacao,grupo',
            'parent_id' => 'nullable|exists:treatments,id',
            'especialidade' => 'nullable|string|max:100',
            'duracao_padrao' => 'nullable|integer|min:0',
            'preco_base' => 'nullable|numeric|min:0',
            'descricao' => 'nullable|string',
            'cor' => 'nullable|string|max:7',
            'ordem' => 'nullable|integer|min:0',
        ]);

        $changes = [];
        foreach ($validated as $key => $value) {
            $old = $treatment->{$key};
            if ((string) $old !== (string) $value) {
                $changes[$key] = ['from' => $old, 'to' => $value];
            }
        }

        $treatment->update($validated);

        if (! empty($changes)) {
            $this->logAudit($treatment, 'updated', ['changes' => $changes]);
        }

        return redirect()
            ->route('treatments.show', $treatment)
            ->with('success', 'Procedimento atualizado!');
    }

    public function deactivate(Treatment $treatment)
    {
        if (! $treatment->ativo) {
            return back()->with('error', 'Procedimento já está desativado.');
        }

        $treatment->update([
            'ativo' => false,
            'deactivated_at' => now(),
            'deactivated_by_id' => auth()->id(),
        ]);

        $this->logAudit($treatment, 'deactivated');

        return back()->with('success', 'Procedimento desativado. Histórico preservado.');
    }

    public function reactivate(Treatment $treatment)
    {
        if ($treatment->ativo) {
            return back()->with('error', 'Procedimento já está ativo.');
        }

        $treatment->update([
            'ativo' => true,
            'deactivated_at' => null,
            'deactivated_by_id' => null,
        ]);

        $this->logAudit($treatment, 'reactivated');

        return back()->with('success', 'Procedimento reativado.');
    }

    public function destroy(Treatment $treatment, TreatmentStatsService $statsService)
    {
        if ($statsService->hasLinkedAttendances($treatment)) {
            return back()->with('error', 'linked_attendances');
        }

        $nome = $treatment->nome;
        $this->logAudit($treatment, 'deleted', ['nome' => $nome]);
        $treatment->delete();

        return redirect()
            ->route('treatments.index')
            ->with('success', "Procedimento \"{$nome}\" excluído permanentemente.");
    }

    private function groupByCategory($treatments): array
    {
        return $treatments
            ->groupBy('categoria')
            ->map(fn ($items, $categoria) => [
                'categoria' => $categoria ?: 'Outros',
                'cor' => $items->first()->cor ?? '#10b981',
                'items' => $items->values(),
            ])
            ->values()
            ->all();
    }

    private function formatAuditSummary(TreatmentAuditLog $log): string
    {
        if ($log->action === 'updated' && isset($log->metadata['changes']['preco_base'])) {
            $c = $log->metadata['changes']['preco_base'];
            $from = number_format((float) $c['from'], 2, ',', '.');
            $to = number_format((float) $c['to'], 2, ',', '.');

            return "Alterou preço: R$ {$from} → R$ {$to}";
        }

        if ($log->action === 'updated' && ! empty($log->metadata['changes'])) {
            $fields = implode(', ', array_keys($log->metadata['changes']));

            return "Alterou: {$fields}";
        }

        if ($log->action === 'created' && isset($log->metadata['nome'])) {
            return "Criou procedimento: {$log->metadata['nome']}";
        }

        return TreatmentAuditLog::ACTIONS[$log->action] ?? $log->action;
    }

    private function logAudit(Treatment $treatment, string $action, array $metadata = []): void
    {
        TreatmentAuditLog::create([
            'clinic_id' => $treatment->clinic_id,
            'treatment_id' => $treatment->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}