<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Consultation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class ConsultationController extends Controller
{
    public function index(Request $request)
    {
        $query = Consultation::query()
            ->with(['patient', 'professional', 'appointment'])
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

        return redirect()
            ->route('consultations.show', $consultation)
            ->with('success', 'Check-in realizado. Paciente em aguardando.');
    }

    public function show(Consultation $consultation)
    {
        $consultation->load(['patient', 'professional', 'appointment', 'medicalRecord']);

        $treatments = \App\Models\Treatment::where('clinic_id', $consultation->clinic_id)
            ->where('ativo', true)
            ->select('id', 'nome', 'duracao_padrao')
            ->get();

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

    public function finish(Request $request, Consultation $consultation)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $consultation->update([
            'status' => 'finalizado',
            'finished_at' => now(),
            'notes' => $validated['notes'] ?? $consultation->notes,
        ]);

        return redirect()
            ->route('consultations.index')
            ->with('success', 'Consulta finalizada.');
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
    public function addExecution(Request $request, Consultation $consultation)
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

        // Basic stock consumption
        foreach ($treatment->materials as $materialPivot) {
            $item = $materialPivot->pivot->inventory_item_id ? \App\Models\InventoryItem::find($materialPivot->pivot->inventory_item_id) : null;
            if ($item) {
                $qty = $materialPivot->pivot->quantidade ?? 1;
                $item->decrement('quantidade', $qty);
            }
        }

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
