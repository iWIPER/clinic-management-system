<?php

namespace App\Http\Controllers;

use App\Models\Chair;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ChairController extends Controller
{
    public function index()
    {
        // Filtro explícito de clinic_id (não só o global scope) — mesma
        // cautela de ChairPolicy: nunca depender só do scope pra isolamento
        // entre clínicas.
        $chairs = Chair::where('clinic_id', session('current_clinic_id'))
            ->withCount('appointments')
            ->orderBy('id')
            ->get(['id', 'name', 'color']);

        return Inertia::render('ClinicSettings/Chairs', [
            'chairs' => $chairs,
            'maxChairs' => Chair::MAX_PER_CLINIC,
        ]);
    }

    public function store(Request $request)
    {
        $clinicId = session('current_clinic_id');

        // Autoridade real do limite — nunca confiar só no frontend. Checado
        // antes da validação normal pra devolver uma mensagem amigável e
        // não criar nada, nem parcialmente.
        if (Chair::countForClinic($clinicId) >= Chair::MAX_PER_CLINIC) {
            return response()->json([
                'message' => 'Sua clínica já possui o máximo de ' . Chair::MAX_PER_CLINIC . ' cadeiras.',
                'errors' => ['name' => ['Sua clínica já possui o máximo de ' . Chair::MAX_PER_CLINIC . ' cadeiras.']],
            ], 422);
        }

        $validated = $request->validate([
            'name'  => 'required|string|max:30',
            'color' => 'nullable|string|max:20',
        ]);

        $chair = Chair::create([
            'clinic_id' => $clinicId,
            'name'      => $validated['name'],
            'color'     => $validated['color'] ?? '#0d9488',
        ]);

        return response()->json($chair);
    }

    public function update(Request $request, Chair $chair)
    {
        $this->authorize('update', $chair);

        $validated = $request->validate([
            'name'  => 'required|string|max:30',
            'color' => 'nullable|string|max:20',
        ]);

        $chair->update([
            'name'  => $validated['name'],
            'color' => $validated['color'] ?? $chair->color,
        ]);

        return response()->json($chair);
    }

    public function destroy(Request $request, Chair $chair)
    {
        $this->authorize('delete', $chair);

        $usageCount = $chair->appointments()->count();

        // Cadeira em uso exige confirmação explícita (force=true) — mesmo
        // padrão de TaskLabelController::destroy(). Devolve a contagem em
        // vez de excluir, pra tela avisar quantos agendamentos seriam
        // desvinculados antes do usuário decidir.
        if ($usageCount > 0 && ! $request->boolean('force')) {
            return response()->json(['usage_count' => $usageCount], 409);
        }

        DB::transaction(function () use ($chair) {
            // Nenhum agendamento é apagado — só perde a cadeira e volta a
            // aparecer como "Sem cadeira" (nullOnDelete cuidaria disso de
            // qualquer forma, isto só torna explícito antes do delete).
            $chair->appointments()->update(['chair_id' => null]);
            $chair->delete();
        });

        return response()->json(['id' => $chair->id]);
    }

}
