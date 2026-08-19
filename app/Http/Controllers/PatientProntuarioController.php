<?php

namespace App\Http\Controllers;

use App\Models\ClinicalEvolution;
use App\Models\Patient;
use App\Models\PatientAnamnesis;
use App\Models\PatientOdontogram;
use App\Models\PatientTreatment;
use App\Services\PatientProntuarioPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PatientProntuarioController extends Controller
{
    public function show(Patient $patient)
    {
        $patient->load([
            'anamnesis',
            'odontogram',
            'evolutions.professional:id,name',
            'clinicalRecords.professional:id,name',
            'consultations.professional:id,name',
            'photos',
            'clinic',
        ]);

        $anamnesis = $patient->anamnesis ?? PatientAnamnesis::make([
            'patient_id' => $patient->id,
            'clinic_id' => $patient->clinic_id,
        ]);

        $odontogram = $patient->odontogram ?? PatientOdontogram::make([
            'patient_id' => $patient->id,
            'clinic_id' => $patient->clinic_id,
            'teeth_data' => PatientOdontogram::defaultTeethData(),
        ]);

        $clinic = $patient->clinic;

        return Inertia::render('Prontuario/Show', [
            'patient'        => $patient,
            'anamnesis'      => $anamnesis,
            'odontogram'     => $odontogram,
            'toothStatuses'  => collect(PatientOdontogram::TOOTH_STATUSES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'fdiTeeth'       => PatientOdontogram::FDI_TEETH,
            'treatmentsByTooth' => PatientTreatment::groupedByTooth($patient->id),
            'clinicBranding' => [
                'logoUrl' => $clinic?->logoUrl(),
                'name'    => $clinic?->displayName(),
                'slogan'  => $clinic?->slogan,
            ],
        ]);
    }

    public function updateAnamnesis(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'queixa_principal' => 'nullable|string',
            'historico_medico' => 'nullable|string',
            'alergias' => 'nullable|string',
            'medicamentos_em_uso' => 'nullable|string',
            'doencas_sistemicas' => 'nullable|string',
            'historico_familiar' => 'nullable|string',
            'gestante' => 'boolean',
            'hipertensao' => 'boolean',
            'diabetes' => 'boolean',
            'cardiopatia' => 'boolean',
            'hemorragia' => 'boolean',
            'fumo' => 'boolean',
            'alcool' => 'boolean',
            'habitos_outros' => 'nullable|string',
            'cirurgias_previas' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        PatientAnamnesis::updateOrCreate(
            ['patient_id' => $patient->id],
            array_merge($validated, [
                'clinic_id' => $patient->clinic_id,
                'updated_by_id' => auth()->id(),
            ])
        );

        return back()->with('success', 'Anamnese salva com sucesso.');
    }

    public function storeEvolution(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'recorded_at' => 'nullable|date',
        ]);

        ClinicalEvolution::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'professional_id' => auth()->id(),
            'content' => $validated['content'],
            'recorded_at' => $validated['recorded_at'] ?? now(),
        ]);

        return back()->with('success', 'Evolução registrada.');
    }

    public function updateOdontogram(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'teeth_data' => 'required|array',
            'notes' => 'nullable|string',
        ]);

        PatientOdontogram::updateOrCreate(
            ['patient_id' => $patient->id],
            [
                'clinic_id' => $patient->clinic_id,
                'teeth_data' => $validated['teeth_data'],
                'notes' => $validated['notes'] ?? null,
                'updated_by_id' => auth()->id(),
            ]
        );

        return back()->with('success', 'Odontograma atualizado.');
    }

    public function generatePdf(Patient $patient, PatientProntuarioPdfService $pdfService)
    {
        $path = $pdfService->generate($patient);

        return Storage::disk('s3')->download(
            $path,
            'prontuario-' . $patient->id . '-' . now()->format('Y-m-d') . '.pdf'
        );
    }
}