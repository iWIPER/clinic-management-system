<?php

namespace App\Services;

use App\Enums\ClinicalRecordStatus;
use App\Models\Appointment;
use App\Models\Budget;
use App\Models\ClinicalEvolution;
use App\Models\ClinicalRecord;
use App\Models\Patient;
use App\Models\PatientAnamnesis;
use App\Models\PatientOdontogram;
use App\Models\PatientPhoto;
use App\Models\ProcedureExecution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PatientHubService
{
    private const HIGH_VALUE_THRESHOLD = 5000;

    private const CATEGORY_MAP = [
        'Ortodontia' => 'Ortodontia',
        'Implantodontia' => 'Implante',
        'Prótese' => 'Prótese',
        'Cirurgia' => 'Cirurgia',
        'Endodontia' => 'Endodontia',
        'Periodontia' => 'Periodontia',
        'Estética' => 'Estética',
        'Dentística' => 'Estética',
    ];

    public function build(Patient $patient): array
    {
        $this->ensureRelations($patient);

        $timeline = $this->timeline($patient);

        return [
            'badges' => $this->badges($patient),
            'clinicalAlerts' => $this->clinicalAlerts($patient),
            'summary' => [
                'financial' => $this->financialSummary($patient),
                'clinical' => $this->clinicalSummary($patient),
                'relationship' => $this->relationshipSummary($patient),
            ],
            'professionals' => $this->professionalsSummary($patient),
            'tags' => $this->tags($patient),
            'timeline' => $timeline,
            'recentTimeline' => array_slice($timeline, 0, 6),
            'treatments' => $this->treatments($patient),
            'consultations' => $this->consultationsGrouped($patient),
            'financialHistory' => $this->financialHistory($patient),
            'documents' => $this->documents($patient),
            'aiInsights' => $this->aiInsights($patient),
            'toothHistory' => $this->toothHistory($patient),
            'birthday' => $this->birthdayInfo($patient),
        ];
    }

    /**
     * Fase B4: 'appointments.consultation.procedureExecutions.treatment' e
     * 'consultations.professional/.appointment.treatment/.procedureExecutions.treatment'
     * eram carregadas mas nunca acessadas em nenhum método desta classe —
     * formatAppointment() (o único lugar que formata um Appointment) só lê
     * ->treatment e ->professional; os demais usos de $patient->consultations
     * são só count()/where()/contains() sobre a coleção base, sem tocar em
     * relações aninhadas. 'consultations' (sem dot) continua carregada para
     * esses agregados. Medido: 8 das 9 queries desse bloco eram descartadas.
     */
    private function ensureRelations(Patient $patient): void
    {
        $patient->loadMissing([
            'anamnesis.updatedBy:id,name',
            'anamnesisInstances.professional:id,name',
            'odontogram.updatedBy:id,name',
            'appointments.treatment',
            'appointments.professional:id,name',
            'consultations',
            'clinicalRecords.professional:id,name',
            'evolutions.professional:id,name',
            'photos',
            'budgets.items.treatment',
            'responsibleProfessional:id,name,job_title',
            'treatments.professional:id,name',
            'treatments.convenio',
            'convenio',
        ]);
    }

    /**
     * Fase B3: a página do Odontograma (PatientOdontogramController) só
     * consome hub.timeline e hub.treatments — nunca os outros 12 campos que
     * build() calcula (badges, clinicalAlerts, summary, professionals, tags,
     * consultations, financialHistory, documents, aiInsights, toothHistory,
     * birthday). Medido: build() completo custa 24 queries no cenário de
     * teste; timeline()+treatments() precisam de só 7 relações (11 queries).
     * Não remover daqui sem antes conferir timeline()/treatments() — cada
     * relação abaixo corresponde a um acesso real dentro desses dois métodos.
     */
    public function buildForOdontogram(Patient $patient): array
    {
        $this->ensureOdontogramRelations($patient);

        return [
            'timeline' => $this->timeline($patient),
            'treatments' => $this->treatments($patient),
        ];
    }

    private function ensureOdontogramRelations(Patient $patient): void
    {
        $patient->loadMissing([
            'anamnesisInstances.professional:id,name',
            'odontogram.updatedBy:id,name',
            'clinicalRecords.professional:id,name',
            'photos',
            'budgets.items.treatment',
            'treatments.professional:id,name',
            'treatments.convenio',
        ]);
    }

    /**
     * Conjunto enxuto e deliberadamente curado — só o que não é visível em
     * nenhum outro lugar da ficha (status, "próxima consulta" etc. já aparecem
     * em Dados Pessoais/listagem/card Clínico, então não viram badge de novo).
     */
    public function badges(Patient $patient): array
    {
        $badges = [];

        if ($this->hasActiveTreatment($patient)) {
            $badges[] = ['key' => 'treatment_active', 'label' => 'Tratamento em andamento', 'color' => 'blue'];
        }

        if (count($this->clinicalAlerts($patient)) > 0) {
            $badges[] = ['key' => 'clinical_alert', 'label' => 'Alerta clínico', 'color' => 'orange'];
        }

        if (! empty($patient->convenio)) {
            $badges[] = ['key' => 'has_convenio', 'label' => 'Convênio', 'color' => 'indigo'];
        }

        $lastVisit = $this->lastVisitDate($patient);
        if ($lastVisit && $lastVisit->lt(now()->subMonths(6))) {
            $badges[] = ['key' => 'inactive_6m', 'label' => 'Sem retorno há 6+ meses', 'color' => 'purple'];
        }

        if ($patient->photos->contains('categoria', 'Radiografias')) {
            $badges[] = ['key' => 'radiographs_available', 'label' => 'Radiografias', 'color' => 'slate'];
        }

        if ($this->financialSummary($patient)['total_pending'] > 0) {
            $badges[] = ['key' => 'financial_pending', 'label' => 'Pendência financeira', 'color' => 'red'];
        }

        $hasSignedAnamnesis = $patient->anamnesisInstances->contains(fn ($a) => ! is_null($a->signed_at));
        if (! $hasSignedAnamnesis) {
            $badges[] = ['key' => 'anamnesis_pending', 'label' => 'Anamnese pendente', 'color' => 'amber'];
        }

        $hasPendingDocs = collect($this->documents($patient))
            ->pluck('documents')
            ->flatten(1)
            ->contains(fn ($d) => $d['status'] === 'pendente');
        if ($hasPendingDocs) {
            $badges[] = ['key' => 'document_pending', 'label' => 'Documento aguardando assinatura', 'color' => 'amber'];
        }

        return $badges;
    }

    public function clinicalAlerts(Patient $patient): array
    {
        $anamnesis = $patient->anamnesis;
        if (! $anamnesis) {
            return [];
        }

        $alerts = [];

        if ($anamnesis->alergias) {
            $alerts[] = ['key' => 'allergy', 'label' => 'Alergia', 'detail' => $anamnesis->alergias, 'severity' => 'high'];
        }
        if ($anamnesis->hipertensao) {
            $alerts[] = ['key' => 'hypertension', 'label' => 'Hipertensão', 'detail' => 'Paciente hipertenso', 'severity' => 'medium'];
        }
        if ($anamnesis->diabetes) {
            $alerts[] = ['key' => 'diabetes', 'label' => 'Diabetes', 'detail' => 'Paciente diabético', 'severity' => 'medium'];
        }
        if ($anamnesis->gestante) {
            $alerts[] = ['key' => 'pregnant', 'label' => 'Gestante', 'detail' => 'Paciente gestante', 'severity' => 'high'];
        }
        if ($anamnesis->hemorragia) {
            $alerts[] = ['key' => 'bleeding', 'label' => 'Risco de hemorragia', 'detail' => 'Histórico de hemorragia', 'severity' => 'high'];
        }
        if ($anamnesis->cardiopatia) {
            $alerts[] = ['key' => 'cardiac', 'label' => 'Cardiopatia', 'detail' => 'Paciente cardiopata', 'severity' => 'medium'];
        }
        if ($anamnesis->medicamentos_em_uso) {
            $alerts[] = ['key' => 'medication', 'label' => 'Medicamentos em uso', 'detail' => $anamnesis->medicamentos_em_uso, 'severity' => 'low'];
        }

        return $alerts;
    }

    /**
     * "Responsável atual" é um campo persistido (patients.responsible_professional_id),
     * nunca alterado automaticamente por este service — só via ação manual explícita
     * (PatientController::updateResponsibleProfessional). Primeiro/último atendimento
     * seguem a mesma precedência já usada em relationshipSummary()/clinicalSummary().
     */
    public function professionalsSummary(Patient $patient): array
    {
        $firstRecord = $patient->clinicalRecords
            ->whereNotNull('finished_at')
            ->sortBy('finished_at')
            ->first();
        $firstAppointment = $patient->appointments->sortBy('start')->first();

        $lastRecord = $patient->clinicalRecords
            ->whereNotNull('finished_at')
            ->sortByDesc('finished_at')
            ->first();
        $lastAppointment = $patient->appointments
            ->where('status', 'completed')
            ->sortByDesc('start')
            ->first();

        $first = $firstRecord ?? $firstAppointment;
        $last = $lastRecord ?? $lastAppointment;

        return [
            'responsible' => $patient->responsibleProfessional ? [
                'id' => $patient->responsibleProfessional->id,
                'name' => $patient->responsibleProfessional->name,
                'job_title' => $patient->responsibleProfessional->job_title,
            ] : null,
            'first_attendance' => $first ? [
                'professional' => $first->professional?->name,
                'date' => ($first->finished_at ?? $first->start)?->toIso8601String(),
            ] : null,
            'last_attendance' => $last ? [
                'professional' => $last->professional?->name,
                'date' => ($last->finished_at ?? $last->start)?->toIso8601String(),
            ] : null,
        ];
    }

    public function financialSummary(Patient $patient): array
    {
        $budgeted = (float) $patient->budgets->sum('total');
        $received = (float) $patient->clinicalRecords
            ->filter(fn ($r) => $r->status === ClinicalRecordStatus::Concluido)
            ->sum('price');
        $pending = max(0, $budgeted - $received);

        $completedCount = $patient->clinicalRecords
            ->filter(fn ($r) => $r->status === ClinicalRecordStatus::Concluido)
            ->count();
        $ticketAvg = $completedCount > 0 ? $received / $completedCount : 0;

        $lastPayment = $patient->clinicalRecords
            ->filter(fn ($r) => $r->status === ClinicalRecordStatus::Concluido && (float) $r->price > 0)
            ->sortByDesc('finished_at')
            ->first();

        return [
            'total_budgeted' => round($budgeted, 2),
            'total_received' => round($received, 2),
            'total_pending' => round($pending, 2),
            'ticket_average' => round($ticketAvg, 2),
            'lifetime_value' => round($this->lifetimeValue($patient), 2),
            'last_payment_at' => $lastPayment?->finished_at?->toIso8601String(),
            'convenio' => $patient->convenio?->nome,
        ];
    }

    public function clinicalSummary(Patient $patient): array
    {
        $completedConsultations = $patient->consultations->where('status', 'finalizado')->count();
        $completedTreatments = $patient->clinicalRecords
            ->filter(fn ($r) => $r->status === ClinicalRecordStatus::Concluido)
            ->count();
        $activeTreatments = $patient->budgets->whereIn('status', ['aprovado', 'convertido'])->count()
            + $patient->consultations->where('status', 'em_atendimento')->count();

        $lastRecord = $patient->clinicalRecords->sortByDesc('finished_at')->first();
        $lastTooth = $this->lastTreatedTooth($patient);

        $lastVisit = $this->lastVisitDate($patient);
        $nextAppointment = $patient->appointments
            ->where('start', '>', now())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->sortBy('start')
            ->first();

        $firstVisit = $patient->clinicalRecords->min('finished_at')
            ?? $patient->appointments->min('start');

        $lastSignedAnamnesis = $patient->anamnesisInstances
            ->whereNotNull('signed_at')
            ->sortByDesc('signed_at')
            ->first();

        return [
            'consultations_completed' => $completedConsultations,
            'treatments_completed' => $completedTreatments,
            'treatments_active' => $activeTreatments,
            'last_procedure' => $lastRecord?->procedure_name,
            'last_tooth' => $lastTooth,
            'first_visit_at' => $firstVisit ? Carbon::parse($firstVisit)->toIso8601String() : null,
            'last_visit_at' => $lastVisit?->toIso8601String(),
            'next_appointment_at' => $nextAppointment?->start?->toIso8601String(),
            'next_appointment_label' => $nextAppointment
                ? ($nextAppointment->treatment?->nome ?? 'Consulta')
                : null,
            'odontogram_updated_at' => $patient->odontogram?->updated_at?->toIso8601String(),
            'odontogram_updated_by' => $patient->odontogram?->updatedBy?->name,
            'last_anamnesis_at' => $lastSignedAnamnesis?->signed_at?->toIso8601String(),
            'last_anamnesis_signed_by' => $lastSignedAnamnesis?->professional?->name,
        ];
    }

    public function relationshipSummary(Patient $patient): array
    {
        $appointments = $patient->appointments;
        $total = $appointments->count();
        $completed = $appointments->where('status', 'completed')->count();
        $noShows = $appointments->where('status', 'no_show')->count();
        $cancelled = $appointments->where('status', 'cancelled')->count();

        $attendanceRate = $total > 0
            ? round(($completed / max(1, $total - $cancelled)) * 100, 1)
            : 100;

        $firstVisit = $patient->clinicalRecords->min('finished_at')
            ?? $patient->appointments->min('start');

        $patientSince = $patient->created_at;

        $reschedules = (int) $appointments->sum('reschedule_count');

        $lastContact = collect([
            $appointments->max('created_at'),
            $patient->consultations->max('created_at'),
            $patient->evolutions->max('recorded_at'),
        ])->filter()->max();

        return [
            'attendances' => $completed,
            'attendance_rate' => $attendanceRate,
            'no_shows' => $noShows,
            'cancellations' => $cancelled,
            'reschedules' => $reschedules,
            'time_as_patient' => $patientSince ? $this->humanizeDuration($patientSince) : null,
            'first_visit_at' => $firstVisit ? Carbon::parse($firstVisit)->toIso8601String() : null,
            'last_contact_at' => $lastContact ? Carbon::parse($lastContact)->toIso8601String() : null,
        ];
    }

    public function tags(Patient $patient): array
    {
        $tags = [];

        foreach ($patient->clinicalRecords as $record) {
            $cat = self::CATEGORY_MAP[$record->procedure_category] ?? null;
            if ($cat && ! in_array($cat, $tags, true)) {
                $tags[] = $cat;
            }
        }

        if ($this->lifetimeValue($patient) >= self::HIGH_VALUE_THRESHOLD) {
            $tags[] = 'Alto Valor';
        }
        if ($this->hasOverdueBudgets($patient)) {
            $tags[] = 'Inadimplente';
        }
        if ($this->needsReturn($patient)) {
            $tags[] = 'Retorno Pendente';
        }
        if ($this->lifetimeValue($patient) >= 10000) {
            $tags[] = 'VIP';
        }

        return array_values(array_unique($tags));
    }

    /**
     * Linha do tempo — deliberadamente restrita a só 5 tipos de evento
     * (pagamento, documento clínico, anamnese concluída, odontograma
     * atualizado, radiografia). Consultas/atendimentos/procedimentos/fotos
     * clínicas/mudança de responsável têm seus próprios módulos (Agenda,
     * Consultas, Financeiro, Fotos Clínicas, card Profissionais) e foram
     * removidos daqui de propósito para não duplicar informação — aqui é só
     * o que merece aparecer como marco na jornada clínica do paciente.
     */
    public function timeline(Patient $patient): array
    {
        $events = collect();

        foreach ($patient->clinicalRecords as $record) {
            if ($record->finished_at && (float) $record->price > 0) {
                $events->push([
                    'type' => 'payment_received',
                    'category' => 'financeiro',
                    'occurred_at' => $record->finished_at->copy()->addMinutes(2)->toIso8601String(),
                    'title' => 'Pagamento registrado',
                    'detail' => 'R$ ' . number_format((float) $record->price, 2, ',', '.'),
                    'meta' => ['amount' => (float) $record->price, 'method' => 'Consultório'],
                ]);
            }
        }

        foreach ($patient->anamnesisInstances as $instance) {
            if ($instance->completed_at) {
                $events->push([
                    'type' => 'anamnesis_completed',
                    'category' => 'clinico',
                    'occurred_at' => $instance->completed_at->toIso8601String(),
                    'title' => 'Anamnese concluída',
                    'detail' => $instance->professional?->name,
                    'meta' => ['instance_id' => $instance->id],
                ]);
            }
        }

        if ($patient->odontogram && $patient->odontogram->updated_at->gt($patient->odontogram->created_at->addMinute())) {
            $events->push([
                'type' => 'odontogram_updated',
                'category' => 'clinico',
                'occurred_at' => $patient->odontogram->updated_at->toIso8601String(),
                'title' => 'Odontograma atualizado',
                'detail' => $patient->odontogram->updatedBy?->name,
                'meta' => [],
            ]);
        }

        $photoEventMap = [
            'Radiografias' => 'Radiografia adicionada',
            'Documentação' => 'Documento clínico enviado',
        ];
        foreach ($patient->photos as $photo) {
            $title = $photoEventMap[$photo->categoria] ?? null;
            if (! $title) {
                continue;
            }

            $events->push([
                'type' => 'file_added',
                'category' => $photo->categoria === 'Documentação' ? 'documentos' : 'arquivos',
                'occurred_at' => ($photo->taken_at ?? $photo->created_at)->toIso8601String(),
                'title' => $title,
                'detail' => $photo->subcategoria ?? $photo->filename,
                'meta' => ['photo_id' => $photo->id, 'dente' => $photo->dente],
            ]);
        }

        return $events
            ->sortByDesc('occurred_at')
            ->values()
            ->all();
    }

    public function treatments(Patient $patient): array
    {
        $items = collect();

        foreach ($patient->clinicalRecords as $record) {
            $items->push([
                'id' => 'record-' . $record->id,
                'source' => 'clinical_record',
                'name' => $record->procedure_name,
                'category' => $record->procedure_category,
                'description' => $record->notes,
                'tooth' => null,
                'faces' => null,
                'professional' => $record->professional?->name,
                'started_at' => $record->started_at?->toIso8601String(),
                'finished_at' => $record->finished_at?->toIso8601String(),
                'value' => (float) $record->price,
                'payment_method' => 'Consultório',
                'status' => $record->status === ClinicalRecordStatus::Concluido ? 'concluido' : ($record->status === ClinicalRecordStatus::Cancelado ? 'cancelado' : 'em_andamento'),
                'notes' => $record->notes,
            ]);
        }

        foreach ($patient->budgets as $budget) {
            foreach ($budget->items as $item) {
                $status = match ($budget->status) {
                    'aprovado', 'convertido' => 'planejado',
                    'rejeitado' => 'cancelado',
                    default => 'planejado',
                };

                $items->push([
                    'id' => 'budget-item-' . $item->id,
                    'source' => 'budget',
                    'name' => $item->treatment?->nome ?? $item->descricao,
                    'category' => $item->treatment?->categoria,
                    'description' => $item->treatment?->descricao,
                    'tooth' => null,
                    'faces' => null,
                    'professional' => null,
                    'started_at' => $budget->created_at->toIso8601String(),
                    'finished_at' => null,
                    'value' => (float) $item->total,
                    'payment_method' => null,
                    'status' => $status,
                    'notes' => $budget->notes,
                ]);
            }
        }

        foreach ($patient->treatments as $treatment) {
            $items->push([
                'id' => 'patient-treatment-' . $treatment->id,
                'source' => 'patient_treatment',
                'budget_code' => $treatment->budget_code,
                'name' => $treatment->procedure_name,
                'category' => null,
                'description' => $treatment->notes,
                'tooth' => $treatment->tooth,
                'faces' => $treatment->faces,
                'professional' => $treatment->professional?->name,
                'convenio' => $treatment->convenio?->nome,
                'started_at' => optional($treatment->treatment_date)->toIso8601String(),
                'finished_at' => $treatment->completed_at?->toIso8601String(),
                'value' => (float) $treatment->value_charged,
                'cost' => (float) $treatment->cost,
                'payment_method' => null,
                'status' => $treatment->status,
                'notes' => $treatment->notes,
            ]);
        }

        return $items->sortByDesc('started_at')->values()->all();
    }

    public function consultationsGrouped(Patient $patient): array
    {
        $appointments = $patient->appointments->sortByDesc('start');

        return [
            'completed' => $appointments->where('status', 'completed')->map(fn ($a) => $this->formatAppointment($a))->values()->all(),
            'upcoming' => $appointments->where('start', '>', now())->whereIn('status', ['scheduled', 'confirmed'])->map(fn ($a) => $this->formatAppointment($a))->values()->all(),
            'cancelled' => $appointments->where('status', 'cancelled')->map(fn ($a) => $this->formatAppointment($a))->values()->all(),
            'no_show' => $appointments->where('status', 'no_show')->map(fn ($a) => $this->formatAppointment($a))->values()->all(),
        ];
    }

    public function financialHistory(Patient $patient): array
    {
        $payments = $patient->clinicalRecords
            ->filter(fn ($r) => $r->status === ClinicalRecordStatus::Concluido && (float) $r->price > 0)
            ->map(fn ($r) => [
                'type' => 'recebimento',
                'date' => $r->finished_at?->toIso8601String(),
                'description' => $r->procedure_name,
                'amount' => (float) $r->price,
                'method' => 'Consultório',
                'status' => 'pago',
            ]);

        $budgets = $patient->budgets->map(fn ($b) => [
            'type' => 'orcamento',
            'date' => $b->created_at->toIso8601String(),
            'description' => 'Orçamento #' . $b->id,
            'amount' => (float) $b->total,
            'method' => null,
            'status' => $b->status,
        ]);

        $pending = $patient->budgets
            ->whereIn('status', ['aprovado', 'rascunho'])
            ->map(fn ($b) => [
                'type' => 'pendente',
                'date' => $b->valid_until?->toIso8601String() ?? $b->created_at->toIso8601String(),
                'description' => 'Orçamento pendente #' . $b->id,
                'amount' => (float) $b->total,
                'method' => null,
                'status' => $b->valid_until && $b->valid_until->isPast() ? 'atrasado' : 'pendente',
            ]);

        return $payments
            ->concat($budgets)
            ->concat($pending)
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    public function documents(Patient $patient): array
    {
        $docCategories = [
            'Receitas' => ['Receita'],
            'Atestados' => ['Encaminhamento'],
            'Declarações' => ['Documento do Paciente'],
            'Anamneses' => [],
            'Consentimentos' => ['Termo de Consentimento'],
            'LGPD' => ['Documento do Paciente'],
            'Contratos' => ['Contrato'],
            'Documentos personalizados' => ['Outros', 'Solicitação de Exame'],
        ];

        $photos = $patient->photos->where('categoria', 'Documentação');
        $grouped = [];

        foreach ($docCategories as $category => $subcats) {
            $docs = $photos->filter(function ($p) use ($subcats, $category) {
                if ($category === 'Anamneses') {
                    return false;
                }

                return empty($subcats) || in_array($p->subcategoria, $subcats, true);
            })->map(fn ($p) => $this->formatDocument($p))->values();

            if ($category === 'Anamneses' && $patient->anamnesis?->updated_at) {
                $docs->prepend([
                    'id' => 'anamnesis',
                    'name' => 'Anamnese do paciente',
                    'category' => 'Anamneses',
                    'status' => 'assinado',
                    'signed_by' => $patient->anamnesis->updatedBy?->name,
                    'signed_at' => $patient->anamnesis->updated_at->toIso8601String(),
                    'ip' => null,
                    'hash' => null,
                ]);
            }

            $grouped[] = [
                'category' => $category,
                'documents' => $docs->all(),
            ];
        }

        return $grouped;
    }

    public function aiInsights(Patient $patient): array
    {
        $insights = [];

        $lastVisit = $this->lastVisitDate($patient);
        if ($lastVisit && $lastVisit->lt(now()->subMonths(6))) {
            $months = $lastVisit->diffInMonths(now());
            $insights[] = [
                'type' => 'return_overdue',
                'priority' => 'high',
                'message' => "Paciente sem retorno há {$months} meses",
            ];
        }

        $pendingBudget = $patient->budgets->whereIn('status', ['rascunho', 'aprovado'])->sum('total');
        if ($pendingBudget > 0) {
            $insights[] = [
                'type' => 'pending_budget',
                'priority' => 'medium',
                'message' => 'Paciente possui orçamento pendente de R$ ' . number_format($pendingBudget, 2, ',', '.'),
            ];
        }

        if ($this->hasActiveTreatment($patient)) {
            $insights[] = [
                'type' => 'incomplete_treatment',
                'priority' => 'medium',
                'message' => 'Paciente possui tratamento iniciado e não concluído',
            ];
        }

        if ($this->lifetimeValue($patient) >= self::HIGH_VALUE_THRESHOLD && $this->needsReturn($patient)) {
            $insights[] = [
                'type' => 'high_potential',
                'priority' => 'low',
                'message' => 'Paciente com alto potencial de retorno',
            ];
        }

        return $insights;
    }

    public function toothHistory(Patient $patient): array
    {
        $history = [];

        foreach (PatientOdontogram::FDI_TEETH as $tooth) {
            $entries = collect();

            $toothPhotos = $patient->photos->where('dente', (string) $tooth);
            foreach ($toothPhotos as $photo) {
                $entries->push([
                    'year' => ($photo->taken_at ?? $photo->created_at)->year,
                    'procedure' => $photo->subcategoria ?? 'Registro clínico',
                    'date' => ($photo->taken_at ?? $photo->created_at)->toIso8601String(),
                    'professional' => null,
                    'notes' => null,
                    'photo_id' => $photo->id,
                ]);
            }

            $toothTreatments = $patient->treatments->where('tooth', (string) $tooth);
            foreach ($toothTreatments as $treatment) {
                $date = $treatment->completed_at ?? $treatment->treatment_date ?? $treatment->created_at;
                $entries->push([
                    'year' => $date->year,
                    'procedure' => $treatment->procedure_name,
                    'date' => $date->toIso8601String(),
                    'professional' => $treatment->professional?->name,
                    'notes' => $treatment->notes,
                    'status' => $treatment->status,
                    'budget_code' => $treatment->budget_code,
                    'photo_id' => null,
                ]);
            }

            $teethData = $patient->odontogram?->teeth_data[$tooth] ?? null;
            $status = $teethData['status'] ?? 'saudavel';

            $history[$tooth] = [
                'tooth' => $tooth,
                'status' => $status,
                'status_label' => PatientOdontogram::TOOTH_STATUSES[$status] ?? $status,
                'notes' => $teethData['notes'] ?? '',
                'procedures' => $entries->sortByDesc('date')->values()->all(),
                'photos' => $toothPhotos->map(fn ($p) => [
                    'id' => $p->id,
                    'subcategoria' => $p->subcategoria,
                    'categoria' => $p->categoria,
                    'taken_at' => $p->taken_at?->toIso8601String(),
                ])->values()->all(),
            ];
        }

        return $history;
    }

    public function birthdayInfo(Patient $patient): array
    {
        if (! $patient->nascimento) {
            return ['is_upcoming' => false, 'days_until' => null, 'whatsapp_message' => null];
        }

        $today = now()->startOfDay();
        $nextBirthday = $patient->nascimento->copy()->year($today->year);
        if ($nextBirthday->lt($today)) {
            $nextBirthday->addYear();
        }

        $daysUntil = $today->diffInDays($nextBirthday, false);

        $isUpcoming = $daysUntil >= 0 && $daysUntil <= 7;
        $name = $patient->nome;

        $message = "Olá {$name}! 🎂 A equipe deseja um feliz aniversário! Que seu sorriso brilhe ainda mais. Conte conosco para cuidar da sua saúde bucal.";

        return [
            'is_upcoming' => $isUpcoming,
            'days_until' => (int) $daysUntil,
            'date' => $nextBirthday->toDateString(),
            'whatsapp_message' => $message,
            'whatsapp_url' => $patient->telefone
                ? 'https://wa.me/55' . preg_replace('/\D/', '', $patient->telefone) . '?text=' . urlencode($message)
                : null,
        ];
    }

    private function formatAppointment(Appointment $apt): array
    {
        return [
            'id' => $apt->id,
            'start' => $apt->start->toIso8601String(),
            'end' => $apt->end->toIso8601String(),
            'status' => $apt->status,
            'treatment' => $apt->treatment?->nome,
            'professional' => $apt->professional?->name,
            'notes' => $apt->notes,
        ];
    }

    private function formatDocument(PatientPhoto $photo): array
    {
        $signedSubcats = ['Termo de Consentimento', 'Contrato'];

        return [
            'id' => $photo->id,
            'name' => $photo->subcategoria ?? $photo->filename,
            'category' => $photo->categoria,
            'status' => in_array($photo->subcategoria, $signedSubcats, true) ? 'assinado' : 'pendente',
            'signed_by' => null,
            'signed_at' => $photo->taken_at?->toIso8601String(),
            'ip' => null,
            'hash' => $photo->drive_file_id ? substr(md5($photo->drive_file_id), 0, 16) : null,
        ];
    }

    private function lifetimeValue(Patient $patient): float
    {
        return (float) $patient->clinicalRecords
            ->filter(fn ($r) => $r->status === ClinicalRecordStatus::Concluido)
            ->sum('price');
    }

    /**
     * Duração legível baseada em diferença de calendário (ano/mês/dia reais,
     * não uma média de dias) — ex: "2 dias", "3 meses e 12 dias",
     * "2 anos, 3 meses e 8 dias". Nunca um decimal cru.
     */
    private function humanizeDuration(Carbon $since): string
    {
        $interval = $since->diff(now());

        if ($interval->y === 0 && $interval->m === 0 && $interval->d === 0) {
            return 'Hoje';
        }

        $parts = [];
        if ($interval->y > 0) {
            $parts[] = "{$interval->y} " . ($interval->y === 1 ? 'ano' : 'anos');
        }
        if ($interval->m > 0) {
            $parts[] = "{$interval->m} " . ($interval->m === 1 ? 'mês' : 'meses');
        }
        if ($interval->d > 0) {
            $parts[] = "{$interval->d} " . ($interval->d === 1 ? 'dia' : 'dias');
        }

        $last = array_pop($parts);

        return $parts ? implode(', ', $parts) . ' e ' . $last : $last;
    }

    private function lastVisitDate(Patient $patient): ?Carbon
    {
        $dates = collect([
            $patient->clinicalRecords->max('finished_at'),
            $patient->appointments->where('status', 'completed')->max('start'),
        ])->filter();

        return $dates->isNotEmpty() ? Carbon::parse($dates->max()) : null;
    }

    private function lastTreatedTooth(Patient $patient): ?string
    {
        $photo = $patient->photos->whereNotNull('dente')->sortByDesc('taken_at')->first();

        return $photo?->dente;
    }

    private function hasOverdueBudgets(Patient $patient): bool
    {
        return $patient->budgets
            ->whereIn('status', ['aprovado', 'rascunho'])
            ->contains(fn ($b) => $b->valid_until && $b->valid_until->isPast());
    }

    private function hasActiveTreatment(Patient $patient): bool
    {
        return $patient->consultations->contains('status', 'em_atendimento')
            || $patient->budgets->contains('status', 'convertido');
    }

    private function needsReturn(Patient $patient): bool
    {
        $hasFuture = $patient->appointments
            ->where('start', '>', now())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->isNotEmpty();

        if ($hasFuture) {
            return false;
        }

        $lastVisit = $this->lastVisitDate($patient);

        return $lastVisit && $lastVisit->lt(now()->subMonths(3));
    }
}