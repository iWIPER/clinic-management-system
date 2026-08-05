<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientNote;
use App\Models\PatientTag;
use App\Services\PatientNoteService;
use Illuminate\Http\Request;

class PatientNoteController extends Controller
{
    public function __construct(private PatientNoteService $service) {}

    public function store(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'is_pinned' => 'boolean',
            'is_alert' => 'boolean',
            'priority' => 'nullable|string|in:critico,atencao,informativo',
            'tag_ids' => 'array',
            // Observações reutilizam o mesmo vocabulário de marcadores do
            // paciente — mesma regra de PatientMarkerController::sync().
            'tag_ids.*' => PatientTag::markerExistsRule(),
        ]);

        $this->service->store($patient, $validated, (int) auth()->id());

        return back()->with('success', 'Observação registrada.');
    }

    public function update(Request $request, Patient $patient, PatientNote $note)
    {
        abort_unless($note->patient_id === $patient->id, 404);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'is_pinned' => 'boolean',
            'is_alert' => 'boolean',
            'priority' => 'nullable|string|in:critico,atencao,informativo',
            'tag_ids' => 'array',
            // Observações reutilizam o mesmo vocabulário de marcadores do
            // paciente — mesma regra de PatientMarkerController::sync().
            'tag_ids.*' => PatientTag::markerExistsRule(),
        ]);

        $this->service->update($note, $validated);

        return back()->with('success', 'Observação atualizada.');
    }

    public function destroy(Patient $patient, PatientNote $note)
    {
        abort_unless($note->patient_id === $patient->id, 404);
        $note->delete();

        return back()->with('success', 'Observação removida.');
    }
}