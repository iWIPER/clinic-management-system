<?php

namespace App\Http\Controllers;

use App\Models\DriveActivityLog;
use App\Models\Patient;
use App\Models\PatientAnamnesis;
use App\Models\PatientOdontogram;
use App\Services\GoogleDriveService;
use App\Services\PatientHubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    /**
     * Regras completas do formulário de paciente (identificação, endereço,
     * responsável legal, convênio). Reaproveitado por
     * PatientInvitePublicController::draftValidationRules() para a validação
     * relaxada (sem "required") do wizard público de preenchimento de
     * cadastro — ver PATIENT_INVITATIONS_BRD.md §8.1. Público de propósito:
     * é chamado a partir de fora desta classe.
     */
    public function patientValidationRules(): array
    {
        return [
            'nome' => 'required|string|max:100',
            'sobrenome' => 'required|string|max:100',
            'nascimento' => 'nullable|date',
            'sexo' => 'nullable|string|in:masculino,feminino,nao_binario,prefiro_nao_informar',
            'status' => 'nullable|string|in:ativo,inativo,falecido',
            'is_estrangeiro' => 'boolean',
            'cpf' => 'nullable|string|max:20',
            'rg' => 'nullable|string|max:20',
            'passaporte' => 'nullable|string|max:30',
            'profissao' => 'nullable|string|max:100',
            'canal_lembrete' => 'nullable|string|in:whatsapp,sms,email,nao_enviar',
            'telefone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'possui_responsavel_legal' => 'boolean',
            'responsavel_legal_nome' => 'nullable|string|max:100',
            'responsavel_legal_cpf' => 'nullable|string|max:20',
            'responsavel_legal_rg' => 'nullable|string|max:20',
            'responsavel_legal_estrangeiro' => 'boolean',
            'responsavel_legal_passaporte' => 'nullable|string|max:30',
            'responsavel_legal_telefone' => 'nullable|string|max:30',
            'responsavel_legal_parentesco' => 'nullable|string|in:pai,mae,tutor,conjuge,filho,outro',
            'contato_emergencia_nome' => 'nullable|string|max:100',
            'contato_emergencia_telefone' => 'nullable|string|max:30',
            'cep' => 'nullable|string|max:10',
            'logradouro' => 'nullable|string|max:150',
            'numero' => 'nullable|string|max:10',
            'complemento' => 'nullable|string|max:50',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:2',
            'origem' => 'nullable|string|in:manual,indicacao,google,instagram,facebook,whatsapp,site,convenio,outro,convite',
            'convenio_id' => 'nullable|exists:convenios,id',
            'tipo_atendimento' => 'nullable|string|in:particular,convenio,outro',
            'convenio_numero_carteirinha' => 'nullable|string|max:50',
            'convenio_titular' => 'nullable|string|max:100',
            'convenio_titular_cpf' => 'nullable|string|max:20',
            'convenio_titular_parentesco' => 'nullable|string|in:pai,mae,tutor,conjuge,filho,outro',
            'tipo_atendimento_outro_descricao' => 'nullable|string|max:255',
        ];
    }

    public function show(Request $request, Patient $patient, GoogleDriveService $driveService, PatientHubService $hubService)
    {
        $patient->load(['photos']);

        $hub = $hubService->build($patient);

        $anamnesis = $patient->anamnesis ?? PatientAnamnesis::make([
            'patient_id' => $patient->id,
            'clinic_id' => $patient->clinic_id,
        ]);

        $odontogram = $patient->odontogram ?? PatientOdontogram::make([
            'patient_id' => $patient->id,
            'clinic_id' => $patient->clinic_id,
            'teeth_data' => PatientOdontogram::defaultTeethData(),
        ]);

        $clinic           = $patient->clinic;
        $isDriveConnected = $clinic
            && $clinic->storageConnection
            && $clinic->storageConnection->status === 'connected';

        $storageQuota        = $isDriveConnected ? $driveService->getStorageQuota($clinic) : null;
        $disclaimerConfirmed = (bool) $clinic?->storage_disclaimer_confirmed_at;

        $driveActivityLogs = $clinic
            ? DriveActivityLog::where(function ($q) use ($clinic, $patient) {
                $q->where('patient_id', $patient->id)
                  ->orWhere(function ($q2) use ($clinic) {
                      $q2->where('clinic_id', $clinic->id)->whereNull('patient_id');
                  });
            })->latest('created_at')->limit(50)->get()
            : collect();

        return Inertia::render('Patients/Show', [
            'patient'             => $patient,
            'hub'                 => $hub,
            'anamnesis'           => $anamnesis,
            'odontogram'          => $odontogram,
            'toothStatuses'       => collect(PatientOdontogram::TOOTH_STATUSES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'fdiTeeth'            => PatientOdontogram::FDI_TEETH,
            'activeTab'           => $request->input('tab', 'timeline'),
            'clinicId'            => $clinic?->id,
            'isDriveConnected'    => $isDriveConnected,
            'storageQuota'        => $storageQuota,
            'disclaimerConfirmed' => $disclaimerConfirmed,
            'driveActivityLogs'   => $driveActivityLogs,
        ]);
    }

    public function edit(Patient $patient)
    {
        // DUMP 1: dados brutos do banco (via route model binding)
        Log::debug('[PatientController@edit] DUMP 1 — patient.toArray()', $patient->toArray());

        $payload = $patient->only([
            'id', 'clinic_id',
            'nome', 'sobrenome', 'nascimento', 'status',
            'doc_tipo', 'doc_numero',
            'telefone', 'email',
            'contato_emergencia_nome', 'contato_emergencia_telefone',
            'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado',
            'observacoes',
            'updated_at',
        ]);

        // DUMP 2: payload que será enviado ao Inertia
        Log::debug('[PatientController@edit] DUMP 2 — Inertia payload', $payload);

        return Inertia::render('Patients/Edit', [
            'patient' => $payload,
        ]);
    }

    public function update(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:100',
            'sobrenome' => 'required|string|max:100',
            'nascimento' => 'nullable|date',
            'status' => 'nullable|string|in:ativo,inativo,falecido',
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
        $patient->refresh();

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
