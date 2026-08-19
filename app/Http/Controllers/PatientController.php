<?php

namespace App\Http\Controllers;

use App\Exports\PatientsExport;
use App\Models\AnamnesisTemplate;
use App\Models\Convenio;
use App\Models\DriveActivityLog;
use App\Models\Patient;
use App\Models\PatientAnamnesis;
use App\Models\PatientOdontogram;
use App\Services\Anamnesis\AnamnesisService;
use App\Services\Documents\DocumentHubService;
use App\Services\GoogleDriveService;
use App\Services\PatientExportService;
use App\Services\PatientHubService;
use App\Services\PatientListingService;
use App\Services\PatientMarkerService;
use App\Services\PatientNoteService;
use App\Services\PatientStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PatientController extends Controller
{
    /**
     * Únicas quantidades de itens por página aceitas na listagem — mesma
     * lista exposta ao front (prop perPageOptions) para o <select> nunca
     * divergir do que o backend realmente aceita.
     */
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public function index(Request $request, PatientMarkerService $markerService, PatientListingService $listingService)
    {
        $search = $request->input('search');
        $markerId = $request->input('marker');

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 10;
        }

        $patients = $listingService->filteredQuery(['search' => $search, 'marker' => $markerId])
            ->with('responsibleProfessional:id,name')
            ->paginate($perPage)
            ->withQueryString();

        $patients->getCollection()->each->append('idade');

        return Inertia::render('Patients/Index', [
            'patients' => [
                'data' => $patients->items(),
                'pagination' => [
                    'current_page' => $patients->currentPage(),
                    'last_page'    => $patients->lastPage(),
                    'total'        => $patients->total(),
                    'per_page'     => $patients->perPage(),
                ],
            ],
            'filters' => [
                'search' => $search,
                'marker' => $markerId,
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'availableMarkers' => $markerService->availableMarkers(session('current_clinic_id')),
            // Modelos de anamnese para o modal "Enviar cadastro ao paciente"
            // (Convites — Fase 1) — não precisa de convênios: a escolha do
            // convênio em si acontece depois, no wizard que o paciente
            // preenche (Fase 2), aqui só existe o checkbox "permitir".
            'anamnesisTemplates' => AnamnesisTemplate::active()->forClinic(session('current_clinic_id'))->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    /**
     * Exporta exatamente os pacientes que a listagem exibiria com os mesmos
     * filtros (mesma PatientListingService::filteredQuery) — hoje isso é
     * "todos", mas quando filtros avançados forem implementados em index(),
     * a exportação passa a respeitá-los automaticamente, sem precisar ser
     * reescrita: os dois consomem a mesma fonte de dados.
     */
    public function export(Request $request, PatientListingService $listingService, PatientExportService $exportService)
    {
        $format = $request->input('format', 'csv');
        abort_unless(in_array($format, ['csv', 'excel'], true), 422, 'Formato de exportação inválido.');

        $patients = $listingService->filteredQuery([
                'search' => $request->input('search'),
                'marker' => $request->input('marker'),
            ])
            ->with(['markers', 'convenio', 'notes', 'responsibleProfessional:id,name'])
            ->withMax('consultations', 'check_in_at')
            ->get();

        $filename = 'pacientes-' . now()->format('Y-m-d');

        if ($format === 'excel') {
            return Excel::download(new PatientsExport($patients, $exportService), "{$filename}.xlsx");
        }

        return $exportService->streamCsv($patients, "{$filename}.csv");
    }

    public function create()
    {
        return Inertia::render('Patients/Create', [
            'convenios' => Convenio::active()->orderBy('ordem')->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->patientValidationRules((int) session('current_clinic_id')));

        $validated['status'] = $validated['status'] ?? 'ativo';
        $validated['origem'] = $validated['origem'] ?? 'manual';
        $validated['created_by_id'] = $request->user()->id;
        $validated['updated_by_id'] = $request->user()->id;

        // Se quem cadastra é Dentista, já assume como responsável pelo paciente;
        // qualquer outro cargo deixa em branco até ser definido manualmente.
        $validated['responsible_professional_id'] = $request->user()->job_title === 'Dentista'
            ? $request->user()->id
            : null;

        $patient = Patient::create($validated);

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Paciente cadastrado com sucesso!');
    }

    /**
     * Regras comuns a store()/update() — único lugar que conhece a forma
     * completa do formulário de paciente, para não duplicar entre os dois.
     * Público porque PatientInvitePublicController também reaproveita (relaxa
     * "required" e filtra por allowlist para o autosave do wizard público —
     * ver PatientInviteService::saveDraftFields()). $clinicId escopa
     * convenio_id à clínica certa em ambos os casos (sessão autenticada ou
     * a clínica do próprio convite, no wizard público) — mantido como regra
     * STRING (não Rule::exists fluente) porque draftValidationRules() faz
     * explode('|', $rule) em cima do valor, e quebraria com um array.
     */
    public function patientValidationRules(int $clinicId): array
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
            'convenio_id' => "nullable|exists:convenios,id,clinic_id,{$clinicId}",
            'tipo_atendimento' => 'nullable|string|in:particular,convenio,outro',
            'convenio_numero_carteirinha' => 'nullable|string|max:50',
            'convenio_titular' => 'nullable|string|max:100',
            'convenio_titular_cpf' => 'nullable|string|max:20',
            'convenio_titular_parentesco' => 'nullable|string|in:pai,mae,tutor,conjuge,filho,outro',
            'tipo_atendimento_outro_descricao' => 'nullable|string|max:255',
        ];
    }

    public function show(
        Request $request,
        Patient $patient,
        GoogleDriveService $driveService,
        PatientHubService $hubService,
        PatientStatusService $statusService,
        AnamnesisService $anamnesisService,
        PatientNoteService $noteService,
        DocumentHubService $documentHubService,
        PatientMarkerService $markerService,
        \App\Services\PatientInviteService $inviteService,
        \App\Services\PatientPaymentService $paymentService,
    ) {
        $this->authorize('view', $patient);

        $anamnesesPage   = max(1, (int) $request->get('anamneses_page', 1));
        $notesPage       = max(1, (int) $request->get('notes_page', 1));
        $documentsPage   = max(1, (int) $request->get('documents_page', 1));
        $treatmentsPage  = max(1, (int) $request->get('treatments_page', 1));
        $evolutionsPage  = max(1, (int) $request->get('evolutions_page', 1));
        $paymentsPage    = max(1, (int) $request->get('payments_page', 1));
        $paymentsStatus  = $request->get('payments_status');
        $paymentsPeriod  = $request->get('payments_period');

        // Contexto barato (sem I/O de rede, sem query pesada) — usado por
        // várias props abaixo. Todo o resto do payload é construído como
        // closures: numa recarga parcial do Inertia (only: [...], usada por
        // todas as abas paginadas — Evoluções, Anamneses, Notas, Tratamentos,
        // Documentos), só as props efetivamente pedidas são executadas. Isso
        // evita, por exemplo, refazer as duas chamadas ao Google Drive
        // (syncPatientLibrary/getStorageQuota) e o PatientHubService::build()
        // (~15 relações carregadas) a cada clique de paginação que só precisa
        // de uma fatia pequena e independente do payload.
        $clinic              = $patient->clinic;
        $isDriveConnected    = $clinic
            && $clinic->storageConnection
            && $clinic->storageConnection->status === 'connected';
        $disclaimerConfirmed = (bool) $clinic?->storage_disclaimer_confirmed_at;
        $doctor              = $request->user();

        // Memoização local: patientNotes/notesPagination são duas props
        // separadas alimentadas pela mesma chamada de serviço — sem isso,
        // uma request que peça as duas (o caso comum) rodaria o serviço duas
        // vezes.
        $notesResult    = null;
        $getNotesResult = function () use (&$notesResult, $noteService, $patient, $notesPage) {
            return $notesResult ??= $noteService->listForPatient($patient, 3, $notesPage);
        };

        return Inertia::render('Patients/Show', [
            'patient' => function () use ($patient, $driveService, $isDriveConnected) {
                if ($isDriveConnected) {
                    try {
                        $driveService->syncPatientLibrary($patient);
                    } catch (\Throwable $e) {
                        Log::warning('[PatientController@show] Falha na sincronização da biblioteca', [
                            'patient_id' => $patient->id,
                            'error'      => $e->getMessage(),
                        ]);
                    }
                }

                $patient->load([
                    'photos',
                    'createdBy:id,name',
                    'updatedBy:id,name',
                    'responsibleProfessional:id,name,job_title,profile_photo_path',
                ]);

                return $patient;
            },
            // Painel de acompanhamento de convite (Convites — Fase 1)
            'latestPatientInvite' => function () use ($patient, $inviteService) {
                $invite = $patient->patientInvites()->latest('created_at')->first();

                if (! $invite) {
                    return null;
                }

                return array_merge(
                    $invite->only(['id', 'kind', 'status', 'progress', 'channel', 'created_at', 'opened_at', 'not_responded_flagged_at']),
                    ['share' => $inviteService->buildShareData($invite)],
                );
            },
            'hub'        => fn () => $hubService->build($patient),
            'anamnesis'  => fn () => $patient->anamnesis ?? PatientAnamnesis::make([
                'patient_id' => $patient->id,
                'clinic_id'  => $patient->clinic_id,
            ]),
            'odontogram' => fn () => $patient->odontogram ?? PatientOdontogram::make([
                'patient_id' => $patient->id,
                'clinic_id'  => $patient->clinic_id,
                'teeth_data' => PatientOdontogram::defaultTeethData(),
            ]),
            'toothStatuses'     => collect(PatientOdontogram::TOOTH_STATUSES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'treatmentsByTooth' => fn () => \App\Models\PatientTreatment::groupedByTooth($patient->id),
            'fdiTeeth'          => PatientOdontogram::FDI_TEETH,
            'activeTab'         => $request->input('tab', 'overview'),
            'anamnesisHub'      => function () use ($anamnesisService, $patient, $anamnesesPage, $clinic) {
                $anamnesisResult = $anamnesisService->listForPatient($patient, 3, $anamnesesPage);

                return [
                    'instances'  => $anamnesisResult['data'],
                    'pagination' => $anamnesisResult['pagination'],
                    'templates'  => $anamnesisService->availableTemplates($clinic?->id),
                    'alerts'     => $anamnesisService->patientCardAlerts($patient),
                ];
            },
            'patientNotes'    => fn () => $getNotesResult()['data'],
            'notesPagination' => fn () => $getNotesResult()['pagination'],
            'noteAlerts'      => fn () => $noteService->alertNotes($patient),
            'patientMarkers'   => fn () => $patient->markers()->get(['patient_tags.id', 'name', 'slug', 'color']),
            'availableMarkers' => fn () => $markerService->availableMarkers($clinic?->id),
            'markerLimit'      => PatientMarkerService::MAX_MARKERS_PER_PATIENT,
            'clinicId'            => $clinic?->id,
            'isDriveConnected'    => $isDriveConnected,
            'storageQuota'        => fn () => $isDriveConnected ? $driveService->getStorageQuota($clinic) : null,
            'disclaimerConfirmed' => $disclaimerConfirmed,
            'driveActivityLogs'   => fn () => $clinic
                ? DriveActivityLog::where(function ($q) use ($clinic, $patient) {
                    $q->where('patient_id', $patient->id)
                      ->orWhere(function ($q2) use ($clinic) {
                          $q2->where('clinic_id', $clinic->id)->whereNull('patient_id');
                      });
                })->latest('created_at')->limit(50)->get()
                : collect(),
            'clinicName' => $clinic?->trade_name ?? $clinic?->name,
            'doctorName' => $doctor?->name,
            'autoStatus' => fn () => $statusService->getAutoStatusData($patient),
            // Equipe responsável: profissionais com consultas não canceladas com este paciente
            'responsibleTeam' => fn () => \App\Models\Appointment::where('patient_id', $patient->id)
                ->whereNotIn('status', ['cancelled'])
                ->with('professional:id,name,email,job_title,profile_photo_path')
                ->get()
                ->pluck('professional')
                ->filter()
                ->unique('id')
                ->map(fn ($u) => [
                    'id'                 => $u->id,
                    'name'               => $u->name,
                    'email'              => $u->email,
                    'job_title'          => $u->job_title,
                    'profile_photo_path' => $u->profile_photo_path,
                ])
                ->values(),
            // Profissionais elegíveis a responsável clínico: apenas usuários ativos da
            // clínica cujo cargo (job_title) seja "Dentista" — política explícita do
            // sistema. Independe do role no vínculo com a clínica (clinic_user.role):
            // um owner ou admin só aparece aqui se seu próprio cargo também for
            // Dentista. Secretário(a), Administrador e Outro nunca são elegíveis.
            'eligibleProfessionals' => fn () => \App\Models\User::query()
                ->join('clinic_user', 'clinic_user.user_id', '=', 'users.id')
                ->where('clinic_user.clinic_id', $clinic?->id)
                ->where('users.job_title', 'Dentista')
                ->where('users.status', 'ativo')
                ->select('users.id', 'users.name', 'users.job_title')
                ->orderBy('users.name')
                ->get(),
            // Aba "Tratamentos" (substitui Histórico de Atendimento na ficha do paciente)
            // Paginado no backend (não só cortado no frontend) — mesmo padrão de
            // AnamnesisService::listForPatient() (data + pagination), reaproveitado
            // pelo componente genérico Pagination.vue no frontend.
            'patientTreatments' => function () use ($patient, $treatmentsPage) {
                $paginator = \App\Models\PatientTreatment::where('patient_id', $patient->id)
                    ->with(['treatment:id,nome', 'professional:id,name', 'convenio:id,nome', 'auditLogs.user:id,name'])
                    ->orderByDesc('treatment_date')
                    ->orderByDesc('id')
                    ->paginate(5, ['*'], 'page', $treatmentsPage);

                return [
                    'data'       => $paginator->items(),
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page'    => $paginator->lastPage(),
                        'total'        => $paginator->total(),
                        'per_page'     => $paginator->perPage(),
                    ],
                ];
            },
            // Card "Evoluções" (sidebar da Visão Geral) — sempre mostra 1 evolução
            // por vez, a mais recente primeiro; paginação de verdade no backend
            // (per_page=1), mesmo padrão de patientTreatments/anamnesisHub acima.
            'evolutionsHub' => function () use ($patient, $evolutionsPage) {
                $paginator = \App\Models\ClinicalEvolution::where('patient_id', $patient->id)
                    ->with(['professional:id,name', 'photos', 'signature'])
                    ->orderByDesc('recorded_at')
                    ->orderByDesc('id')
                    ->paginate(1, ['*'], 'page', $evolutionsPage);

                return [
                    'data'       => $paginator->items(),
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page'    => $paginator->lastPage(),
                        'total'        => $paginator->total(),
                        'per_page'     => $paginator->perPage(),
                    ],
                ];
            },
            'catalogTreatments' => fn () => \App\Models\Treatment::active()->orderBy('nome')->get(['id', 'nome', 'preco_base', 'custo_padrao']),
            'convenios'         => fn () => Convenio::active()->orderBy('ordem')->orderBy('nome')->get(['id', 'nome']),
            'treatmentStatuses' => collect(\App\Models\PatientTreatment::STATUSES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            // Aba "Pagamentos" — mesmo padrão de paginação/filtro de patientTreatments.
            // paymentSummary é sempre o agregado completo do paciente (não filtrado por
            // status), então recarrega junto com a lista para os cards nunca desatualizar.
            'patientPayments' => function () use ($patient, $paymentsPage, $paymentsStatus, $paymentsPeriod) {
                $query = \App\Models\PatientPayment::where('patient_id', $patient->id)
                    ->with(['treatment:id,procedure_name,budget_code,professional_id,value_charged', 'treatment.professional:id,name']);

                if ($paymentsStatus === 'atrasado') {
                    $query->whereIn('status', [\App\Models\PatientPayment::STATUS_PENDENTE, \App\Models\PatientPayment::STATUS_PARCIAL])
                        ->where('due_date', '<', now()->toDateString());
                } elseif (in_array($paymentsStatus, array_keys(\App\Models\PatientPayment::STATUSES), true)) {
                    $query->where('status', $paymentsStatus);
                }

                // Filtro de período por vencimento — só as 3 janelas fixas do
                // spec (sem date-picker livre nesta fase, ver plano aprovado).
                [$periodFrom, $periodTo] = match ($paymentsPeriod) {
                    'este_mes'        => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
                    'mes_passado'     => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
                    'ultimos_90_dias' => [now()->subDays(90)->toDateString(), now()->toDateString()],
                    default           => [null, null],
                };
                if ($periodFrom && $periodTo) {
                    $query->whereBetween('due_date', [$periodFrom, $periodTo]);
                }

                $paginator = $query->orderBy('due_date')->orderBy('id')
                    ->paginate(10, ['*'], 'page', $paymentsPage);

                return [
                    'data'       => $paginator->items(),
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page'    => $paginator->lastPage(),
                        'total'        => $paginator->total(),
                        'per_page'     => $paginator->perPage(),
                    ],
                ];
            },
            'paymentSummary'  => fn () => $paymentService->summary($patient),
            'paymentMethods'  => collect(\App\Models\PatientPayment::METHODS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'paymentStatuses' => collect(\App\Models\PatientPayment::STATUSES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'documentHub' => function () use ($documentHubService, $patient, $documentsPage, $clinic) {
                $documentResult = $documentHubService->listForPatient($patient, 6, $documentsPage);

                return [
                    'documents'  => $documentResult['data'],
                    'pagination' => $documentResult['pagination'],
                    'templates'  => $documentHubService->availableTemplates($clinic?->id),
                    'treatments' => $documentHubService->availableTreatments($clinic?->id),
                ];
            },
        ]);
    }

    public function edit(Patient $patient)
    {
        $this->authorize('update', $patient);

        // DUMP 1: dados brutos do banco (via route model binding)
        Log::debug('[PatientController@edit] DUMP 1 — patient.toArray()', $patient->toArray());

        $payload = $patient->only([
            'id', 'clinic_id',
            'nome', 'sobrenome', 'nascimento', 'sexo', 'status', 'status_automatico',
            'is_estrangeiro', 'cpf', 'rg', 'passaporte', 'profissao', 'canal_lembrete',
            'telefone', 'email',
            'possui_responsavel_legal', 'responsavel_legal_nome', 'responsavel_legal_cpf',
            'responsavel_legal_rg', 'responsavel_legal_estrangeiro', 'responsavel_legal_passaporte',
            'responsavel_legal_telefone', 'responsavel_legal_parentesco',
            'contato_emergencia_nome', 'contato_emergencia_telefone',
            'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado',
            'origem', 'convenio_id', 'tipo_atendimento', 'convenio_numero_carteirinha',
            'convenio_titular', 'convenio_titular_cpf', 'convenio_titular_parentesco',
            'tipo_atendimento_outro_descricao',
            'updated_at',
        ]);

        // DUMP 2: payload que será enviado ao Inertia
        Log::debug('[PatientController@edit] DUMP 2 — Inertia payload', $payload);

        return Inertia::render('Patients/Edit', [
            'patient' => $payload,
            'convenios' => Convenio::active()->orderBy('ordem')->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function update(Request $request, Patient $patient, PatientStatusService $statusService)
    {
        $this->authorize('update', $patient);

        $validated = $request->validate([
            ...$this->patientValidationRules($patient->clinic_id),
            'status_automatico' => 'boolean',
        ]);

        $validated['updated_by_id'] = $request->user()->id;

        $patient->update($validated);
        $patient->refresh();

        if ($patient->status_automatico) {
            $statusService->recalculate($patient);
        }

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Paciente atualizado com sucesso!');
    }

    /**
     * Atribui manualmente o profissional responsável atual pelo paciente.
     * Nunca é chamado automaticamente pelo histórico de atendimentos.
     */
    public function updateResponsibleProfessional(Request $request, Patient $patient)
    {
        $this->authorize('update', $patient);

        $validated = $request->validate([
            'responsible_professional_id' => 'required|exists:users,id',
        ]);

        if (! $patient->clinic->users()->where('users.id', $validated['responsible_professional_id'])->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'responsible_professional_id' => 'Este profissional não pertence a esta clínica.',
            ]);
        }

        $patient->update([
            'responsible_professional_id' => $validated['responsible_professional_id'],
            'updated_by_id'               => $request->user()->id,
        ]);

        \App\Models\AccessLog::record(
            action: 'patient_responsible_professional_updated',
            description: "Profissional responsável por {$patient->nome_completo} atualizado",
            metadata: ['patient_id' => $patient->id, 'professional_id' => $validated['responsible_professional_id']],
        );

        return back()->with('success', 'Profissional responsável atualizado.');
    }

    public function destroy(Patient $patient)
    {
        $this->authorize('delete', $patient);

        $patient->delete();

        return redirect()
            ->route('patients.index')
            ->with('success', 'Paciente removido.');
    }
}
