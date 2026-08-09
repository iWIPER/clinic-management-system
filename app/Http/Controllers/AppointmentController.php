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
        $weekStart = $request->input('week')
            ? Carbon::parse($request->input('week'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $appointments = Appointment::query()
            ->with([
                'patient:id,nome,sobrenome,telefone',
                'professional:id,name',
                'treatment:id,nome,duracao_padrao',
                'consultation:id,appointment_id,status',
            ])
            ->whereBetween('start', [$weekStart->startOfDay(), $weekEnd])
            ->when($request->input('professional_id'), fn ($q, $id) => $q->where('professional_id', $id))
            ->orderBy('start')
            ->get();

        $professionals = User::whereHas('clinics', fn ($q) => $q->where('clinics.id', session('current_clinic_id')))
            ->select('id', 'name')
            ->get();

        return Inertia::render('Appointments/Index', [
            'appointments' => $appointments,
            'professionals' => $professionals,
            'weekStart' => $weekStart->format('Y-m-d'),
            'filters' => $request->only(['professional_id']),
        ]);
    }

    public function fullscreen(Request $request)
    {
        $weekStart = $request->input('week')
            ? Carbon::parse($request->input('week'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $appointments = Appointment::query()
            ->with([
                'patient:id,nome,sobrenome,telefone',
                'professional:id,name',
                'treatment:id,nome,duracao_padrao',
                'consultation:id,appointment_id,status,check_in_at',
            ])
            ->whereBetween('start', [$weekStart->startOfDay(), $weekEnd])
            ->when($request->input('professional_id'), fn ($q, $id) => $q->where('professional_id', $id))
            ->orderBy('start')
            ->get();

        $professionals = User::whereHas('clinics', fn ($q) => $q->where('clinics.id', session('current_clinic_id')))
            ->select('id', 'name')
            ->get();

        return Inertia::render('Appointments/Fullscreen', [
            'appointments' => $appointments,
            'professionals' => $professionals,
            'weekStart' => $weekStart->format('Y-m-d'),
        ]);
    }

    public function create(Request $request)
    {
        $clinicId = session('current_clinic_id');

        $patients = Patient::select('id', 'nome', 'sobrenome', 'telefone')
            ->orderBy('nome')
            ->get();

        $professionals = User::whereHas('clinics', fn ($q) => $q->where('clinics.id', $clinicId))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $treatments = Treatment::where('clinic_id', $clinicId)
            ->forScheduling()
            ->select('id', 'nome', 'duracao_padrao', 'preco_base')
            ->orderBy('nome')
            ->get();

        return Inertia::render('Appointments/Create', [
            'patients' => $patients,
            'professionals' => $professionals,
            'treatments' => $treatments,
            'defaultDate' => $request->input('date', now()->format('Y-m-d')),
            'defaultTime' => $request->input('time', '09:00'),
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
        $professionals = User::whereHas('clinics', fn ($q) => $q->where('clinics.id', $clinicId))
            ->select('id', 'name')->get();
        $treatments = Treatment::where('clinic_id', $clinicId)->forScheduling()
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
            'status' => 'required|in:scheduled,confirmed,in_attendance,cancelled,no_show,completed',
            'notes' => 'nullable|string',
        ]);

        $treatment = Treatment::findOrFail($validated['treatment_id']);
        $start = Carbon::parse($validated['start']);
        $end = $start->copy()->addMinutes($treatment->duracao_padrao ?? 30);

        // Data/hora mudou = remarcação — usado no resumo de relacionamento do paciente.
        $wasRescheduled = ! $appointment->start->equalTo($start);

        $appointment->update([
            'patient_id' => $validated['patient_id'],
            'professional_id' => $validated['professional_id'],
            'treatment_id' => $validated['treatment_id'],
            'start' => $start,
            'end' => $end,
            'status' => $validated['status'],
            'notes' => $validated['notes'],
            'reschedule_count' => $wasRescheduled ? $appointment->reschedule_count + 1 : $appointment->reschedule_count,
        ]);

        return redirect()->route('appointments.index')->with('success', 'Agendamento atualizado!');
    }

    public function checkIn(Appointment $appointment)
    {
        $consultation = \App\Models\Consultation::firstOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'patient_id'      => $appointment->patient_id,
                'professional_id' => $appointment->professional_id,
                'status'          => 'aguardando',
                'check_in_at'     => now(),
            ]
        );

        if (! $consultation->check_in_at) {
            $consultation->update(['check_in_at' => now(), 'status' => 'aguardando']);
        }

        $appointment->update(['status' => 'in_attendance']);

        return back()->with('success', 'Check-in realizado! Paciente aguardando atendimento.');
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'status' => 'required|in:scheduled,confirmed,in_attendance,cancelled,no_show,completed',
        ]);

        $appointment->update(['status' => $validated['status']]);

        return back()->with('success', 'Status atualizado.');
    }

    public function destroy(Appointment $appointment)
    {
        $appointment->delete();

        return redirect()->route('appointments.index')->with('success', 'Agendamento cancelado.');
    }
}
