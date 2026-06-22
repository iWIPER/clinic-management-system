<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $query = Patient::query()->with('consultations'); // eager for future

        // Busca simples conforme spec (nome, CPF/telefone)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('sobrenome', 'like', "%{$search}%")
                  ->orWhere('doc_numero', 'like', "%{$search}%")
                  ->orWhere('telefone', 'like', "%{$search}%");
            });
        }

        $patients = $query->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Patients/Index', [
            'patients' => $patients,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Patients/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:100',
            'sobrenome' => 'required|string|max:100',
            'nascimento' => 'nullable|date',
            'status' => 'nullable|string|in:ativo,inativo,falecido',
            'profissao' => 'nullable|string|max:100',
            'estado_civil' => 'nullable|string|max:50',
            'doc_tipo' => 'nullable|string|max:20',
            'doc_numero' => 'nullable|string|max:30',
            'telefone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'contato_emergencia_nome' => 'nullable|string|max:100',
            'contato_emergencia_telefone' => 'nullable|string|max:20',
            'cep' => 'nullable|string|max:10',
            'logradouro' => 'nullable|string|max:150',
            'numero' => 'nullable|string|max:10',
            'complemento' => 'nullable|string|max:50',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:2',
            'observacoes' => 'nullable|string',
        ]);

        $validated['status'] = $validated['status'] ?? 'ativo';

        $patient = Patient::create($validated);

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Paciente cadastrado com sucesso!');
    }

    public function show(Patient $patient)
    {
        $patient->load(['appointments' => fn($q) => $q->latest()->limit(5), 'consultations', 'photos']);

        $clinic = $patient->clinic;
        $isDriveConnected = $clinic && $clinic->storageConnection && $clinic->storageConnection->status === 'connected';

        return Inertia::render('Patients/Show', [
            'patient' => $patient,
            'isDriveConnected' => $isDriveConnected,
        ]);
    }

    public function edit(Patient $patient)
    {
        return Inertia::render('Patients/Edit', [
            'patient' => $patient,
        ]);
    }

    public function update(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:100',
            'sobrenome' => 'required|string|max:100',
            'nascimento' => 'nullable|date',
            'status' => 'nullable|string|in:ativo,inativo,falecido',
            'profissao' => 'nullable|string|max:100',
            'estado_civil' => 'nullable|string|max:50',
            'doc_tipo' => 'nullable|string|max:20',
            'doc_numero' => 'nullable|string|max:30',
            'telefone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'contato_emergencia_nome' => 'nullable|string|max:100',
            'contato_emergencia_telefone' => 'nullable|string|max:20',
            'cep' => 'nullable|string|max:10',
            'logradouro' => 'nullable|string|max:150',
            'numero' => 'nullable|string|max:10',
            'complemento' => 'nullable|string|max:50',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:2',
            'observacoes' => 'nullable|string',
        ]);

        $patient->update($validated);

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Paciente atualizado com sucesso!');
    }

    public function destroy(Patient $patient)
    {
        $patient->delete();

        return redirect()
            ->route('patients.index')
            ->with('success', 'Paciente removido.');
    }
}
