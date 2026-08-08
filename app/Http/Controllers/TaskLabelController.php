<?php

namespace App\Http\Controllers;

use App\Models\TaskLabel;
use Illuminate\Http\Request;

class TaskLabelController extends Controller
{
    // Teto de bom senso pro seletor de etiquetas continuar rápido de escanear
    // visualmente — sem isso a clínica tende a acumular dezenas de etiquetas
    // quase iguais ao longo do tempo.
    private const MAX_LABELS_PER_CLINIC = 10;

    public function store(Request $request)
    {
        $clinicId = session('current_clinic_id');

        $validated = $request->validate([
            'name'  => 'required|string|max:15',
            'color' => 'nullable|string|max:20',
        ]);

        if (TaskLabel::forClinic($clinicId)->count() >= self::MAX_LABELS_PER_CLINIC) {
            return response()->json([
                'message' => 'Limite de etiquetas atingido. Exclua uma etiqueta existente para criar outra.',
            ], 409);
        }

        $label = TaskLabel::create([
            'clinic_id' => $clinicId,
            'name'      => $validated['name'],
            'color'     => $validated['color'] ?? '#64748b',
        ]);

        return response()->json($label);
    }

    public function destroy(Request $request, TaskLabel $label)
    {
        // Só a clínica dona da etiqueta pode excluí-la — etiquetas globais
        // (clinic_id nulo) nunca batem com a sessão, mesma regra de
        // PatientMarkerController::authorizeClinicOwnership().
        abort_unless(
            (int) $label->clinic_id === (int) session('current_clinic_id'),
            403,
            'Esta etiqueta não pode ser administrada por esta clínica.'
        );

        $usageCount = $label->tasks()->count();

        // Etiqueta em uso exige confirmação explícita (force=true) — devolve
        // a contagem em vez de excluir, pra tela mostrar quantas tarefas
        // seriam afetadas antes do usuário decidir.
        if ($usageCount > 0 && ! $request->boolean('force')) {
            return response()->json(['usage_count' => $usageCount], 409);
        }

        $label->delete();

        return response()->json(['id' => $label->id]);
    }
}
