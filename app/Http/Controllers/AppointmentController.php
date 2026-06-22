<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Appointment::query()
            ->with(['patient', 'professional', 'treatment'])
            ->orderBy('start', 'desc');

        // Filtros
        if ($search = $request->input('search')) {
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('sobrenome', 'like', "%{$search}%");
            });
        }

        if ($professionalId = $request->input('professional_id')) {
            $query->where('professional_id', $professionalId);
        }

        if ($date = $request->input('date')) {
            $query->whereDate('start', $date);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $appointments = $query->paginate(20)->withQueryString();

        // Para filtro por profissional
        $professionals = User::whereHas('clinics', fn($q) => $q->where('clinics.id', session('current_clinic_id')))
            ->select('id', 'name')
            ->get();

        return Inertia::render('Appointments/Index', [
            'appointments' => $appointments,
            'professionals' => $professionals,
            'filters' => $request->only(['search', 'professional_id', 'date', 'status']),
        ]);
    }

    public function create(Request $request)
    {
        $clinicId = session('current_clinic_id');

        $patients = Patient::select('id', 'nome', 'sobrenome', 'telefone')
            ->orderBy('nome')
            ->get();

        $professionals = User::whereHas('clinics', function ($q) use ($clinicId) {
                $q->where('clinics.id', $clinicId);
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $treatments = Treatment::where('clinic_id', $clinicId)
            ->where('ativo', true)
            ->select('id', 'nome', 'duracao_padrao', 'preco_base')
            ->orderBy('nome')
            ->get();

        return Inertia::render('Appointments/Create', [
            'patients' => $patients,
            'professionals' => $professionals,
            'treatments' => $treatments,
            'defaultDate' => $request->input('date', now()->format('Y-m-d')),
            'prefilledPatientId' => $request->input('patient_id'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'professional_id' => 'required|exists:users,id',
            'treatment_id' => 'required|exists:treatments,id',
            'start' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $treatment = Treatment::findOrFail($validated['treatment_id']);

        $start = Carbon::parse($validated['start']);
        $end = $start->copy()->addMinutes($treatment->duracao_padrao ?? 30);

        Appointment::create([
            'patient_id' => $validated['patient_id'],
            'professional_id' => $validated['professional_id'],
            'treatment_id' => $validated['treatment_id'],
            'start' => $start,
            'end' => $end,
            'status' => 'scheduled',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Agendamento criado com sucesso!');
    }

    public function edit(Appointment $appointment)
    {
        $clinicId = session('current_clinic_id');

        $patients = Patient::select('id', 'nome', 'sobrenome')->get();
        $professionals = User::whereHas('clinics', fn($q) => $q->where('clinics.id', $clinicId))
            ->select('id', 'name')->get();
        $treatments = Treatment::where('clinic_id', $clinicId)->where('ativo', true)
            ->select('id', 'nome', 'duracao_padrao')->get();

        return Inertia::render('Appointments/Edit', [
            'appointment' => $appointment->load(['patient', 'professional', 'treatment']),
            'patients' => $patients,
            'professionals' => $professionals,
            'treatments' => $treatments,
        ]);
    }

    public function update(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'professional_id' => 'required|exists:users,id',
            'treatment_id' => 'required|exists:treatments,id',
            'start' => 'required|date',
            'status' => 'required|in:scheduled,confirmed,cancelled,no_show,completed',
            'notes' => 'nullable|string',
        ]);

        $treatment = Treatment::findOrFail($validated['treatment_id']);
        $start = Carbon::parse($validated['start']);
        $end = $start->copy()->addMinutes($treatment->duracao_padrao ?? 30);

        $appointment->update([
            'patient_id' => $validated['patient_id'],
            'professional_id' => $validated['professional_id'],
            'treatment_id' => $validated['treatment_id'],
            'start' => $start,
            'end' => $end,
            'status' => $validated['status'],
            'notes' => $validated['notes'],
        ]);

        return redirect()->route('appointments.index')->with('success', 'Agendamento atualizado!');
    }

    public function destroy(Appointment $appointment)
    {
        $appointment->delete();

        return redirect()->route('appointments.index')->with('success', 'Agendamento cancelado.');
    }
}
