<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\ClinicalEvolution;
use App\Services\ClinicalRecordService;
use App\Services\PatientStatusService;
use App\Services\TreatmentMaterialConsumptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class ConsultationController extends Controller
{
    public function index(Request $request)
    {
        $query = Consultation::query()
            ->where('clinic_id', session('current_clinic_id'))
            ->with(['patient:id,nome,sobrenome', 'professional:id,name'])
            ->orderBy('check_in_at', 'desc');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        } else {
            // Default to active ones
            $query->whereIn('status', ['aguardando', 'em_atendimento']);
        }

        if ($search = $request->input('search')) {
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('sobrenome', 'like', "%{$search}%");
            });
        }

        $consultations = $query->paginate(15)->withQueryString();

        return Inertia::render('Consultations/Index', [
            'consultations' => $consultations,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    /**
     * Check-in: usually called from Appointment or list.
     * Creates a Consultation if not exists, or updates.
     */
    public function checkIn(Appointment $appointment)
    {
        $consultation = Consultation::firstOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'patient_id' => $appointment->patient_id,
                'professional_id' => $appointment->professional_id,
                'status' => 'aguardando',
                'check_in_at' => now(),
            ]
        );

        if (!$consultation->check_in_at) {
            $consultation->update([
                'check_in_at' => now(),
                'status' => 'aguardando',
            ]);
        }

        $appointment->update(['status' => 'in_attendance']);

        return redirect()
            ->route('consultations.show', $consultation)
            ->with('success', 'Check-in realizado. Paciente em aguardando.');
    }

    public function show(Consultation $consultation, \App\Services\TreatmentCatalogService $treatmentCatalogService)
    {
        $consultation->load(['patient', 'professional', 'appointment']);

        $treatments = $treatmentCatalogService->schedulableCatalog($consultation->clinic_id);

        return Inertia::render('Consultations/Show', [
            'consultation' => $consultation,
            'treatments' => $treatments,
        ]);
    }

    public function start(Consultation $consultation)
    {
        $consultation->update([
            'status' => 'em_atendimento',
            'started_at' => now(),
        ]);

        return back()->with('success', 'Atendimento iniciado.');
    }

    public function finish(Request $request, Consultation $consultation, ClinicalRecordService $recordService)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $consultation->update([
            'status' => 'finalizado',
            'finished_at' => now(),
            'notes' => $validated['notes'] ?? $consultation->notes,
        ]);

        if ($consultation->appointment_id) {
            Appointment::where('id', $consultation->appointment_id)->update(['status' => 'completed']);
        }

        $clinicalRecord = $recordService->createFromConsultation($consultation->fresh());

        app(PatientStatusService::class)->recalculate($consultation->patient);

        if ($consultation->notes) {
            ClinicalEvolution::create([
                'clinic_id' => $consultation->clinic_id,
                'patient_id' => $consultation->patient_id,
                'professional_id' => $consultation->professional_id,
                'consultation_id' => $consultation->id,
                'content' => $consultation->notes,
                'recorded_at' => $consultation->finished_at ?? now(),
            ]);
        }

        return redirect()
            ->route('patients.prontuario', $consultation->patient_id)
            ->with('success', 'Atendimento concluído. Prontuário atualizado.');
    }

    public function updateNotes(Request $request, Consultation $consultation)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $consultation->update($validated);

        return back();
    }

    /**
     * Simple update for SOAP or notes (MVP version).
     * Later we can move to dedicated MedicalRecord.
     */
    public function update(Request $request, Consultation $consultation)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            // For basic SOAP in MVP we can store in notes or expand later
        ]);

        $consultation->update($validated);

        return back()->with('success', 'Atualizado.');
    }

    /**
     * Register procedure execution from consultation.
     * This will also consume materials from inventory (basic).
     */
    public function addExecution(Request $request, Consultation $consultation, TreatmentMaterialConsumptionService $stockService)
    {
        $validated = $request->validate([
            'treatment_id' => 'required|exists:treatments,id',
            'notes' => 'nullable|string',
        ]);

        $treatment = \App\Models\Treatment::findOrFail($validated['treatment_id']);

        $execution = \App\Models\ProcedureExecution::create([
            'clinic_id' => $consultation->clinic_id,
            'consultation_id' => $consultation->id,
            'treatment_id' => $treatment->id,
            'executed_at' => now(),
            'price_charged' => $treatment->preco_base,
            'notes' => $validated['notes'],
        ]);

        $stockService->consume($treatment);

        // Auto create receita transaction
        \App\Models\Transaction::create([
            'clinic_id' => $consultation->clinic_id,
            'patient_id' => $consultation->patient_id,
            'tipo' => 'receita',
            'valor' => $treatment->preco_base,
            'categoria' => 'Procedimento',
            'descricao' => $treatment->nome,
            'origem_type' => 'App\\Models\\ProcedureExecution',
            'origem_id' => $execution->id,
            'status' => 'pendente',
        ]);

        return back()->with('success', 'Procedimento registrado, estoque atualizado e lançamento criado.');
    }
}
