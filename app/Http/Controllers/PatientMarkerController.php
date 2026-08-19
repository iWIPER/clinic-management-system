<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientTag;
use App\Services\PatientMarkerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatientMarkerController extends Controller
{
    public function __construct(private PatientMarkerService $service) {}

    /**
     * Cria um marcador no vocabulário da clínica. Não atribui a nenhum
     * paciente — a atribuição é feita separadamente via sync().
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:60',
            'color' => ['required', 'string', Rule::in(PatientMarkerService::PALETTE)],
        ]);

        $this->service->create(session('current_clinic_id'), $validated['name'], $validated['color']);

        return back()->with('success', 'Marcador criado.');
    }

    public function sync(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'marker_ids' => ['array', 'max:' . PatientMarkerService::MAX_MARKERS_PER_PATIENT],
            'marker_ids.*' => PatientTag::markerExistsRule($patient->clinic_id),
        ], [
            'marker_ids.max' => 'O paciente já possui o limite máximo de ' . PatientMarkerService::MAX_MARKERS_PER_PATIENT . ' etiquetas. Remova uma etiqueta antes de adicionar outra.',
        ]);

        $this->service->syncForPatient($patient, $validated['marker_ids'] ?? []);

        return back()->with('success', 'Marcadores atualizados.');
    }

    /**
     * Marcador de sistema: só cor (a customização visual pertence à clínica,
     * mas a linha é global — muda pra todo mundo que usa esse marcador).
     * Marcador da clínica: nome e cor, e precisa ser dono do registro.
     */
    public function update(Request $request, PatientTag $marker)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:60',
            'color' => ['required', 'string', Rule::in(PatientMarkerService::PALETTE)],
        ]);

        if ($marker->is_system) {
            abort_if($validated['name'] !== null, 403, 'Marcadores do sistema não podem ser renomeados.');
        } else {
            $this->authorize('update', $marker);
        }

        $this->service->update($marker, $validated['name'] ?? null, $validated['color']);

        return back()->with('success', 'Marcador atualizado.');
    }

    public function destroy(PatientTag $marker)
    {
        abort_if($marker->is_system, 403, 'Marcadores do sistema não podem ser excluídos.');
        $this->authorize('delete', $marker);

        $this->service->delete($marker);

        return back()->with('success', 'Marcador excluído.');
    }
}
