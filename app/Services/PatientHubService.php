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

        return [
            'badges' => $this->badges($patient),
            'clinicalAlerts' => $this->clinicalAlerts($patient),
            'summary' => [
                'financial' => $this->financialSummary($patient),
                'clinical' => $this->clinicalSummary($patient),
                'relationship' => $this->relationshipSummary($patient),
            ],
            'tags' => $this->tags($patient),
            'timeline' => $this->timeline($patient),
            'treatments' => $this->treatments($patient),
            'consultations' => $this->consultationsGrouped($patient),
            'financialHistory' => $this->financialHistory($patient),
            'documents' => $this->documents($patient),
            'aiInsights' => $this->aiInsights($patient),
            'toothHistory' => $this->toothHistory($patient),
            'birthday' => $this->birthdayInfo($patient),
        ];
    }

    private function ensureRelations(Patient $patient): void
    {
        $patient->loadMissing([
            'anamnesis.updatedBy:id,name',
            'odontogram',
            'appointments.treatment',
            'appointments.professional:id,name',
            'appointments.consultation.procedureExecutions.treatment',
            'consultations.professional:id,name',
            'consultations.appointment.treatment',
            'consultations.procedureExecutions.treatment',
            'clinicalRecords.professional:id,name',
            'evolutions.professional:id,name',
            'photos',
            'budgets.items.treatment',
        ]);
    }

    public function badges(Patient $patient): array
    {
        $badges = [];

        if ($patient->status === 'ativo') {
            $badges[] = ['key' => 'active', 'label' => 'Paciente ativo', 'color' => 'green'];
        }

        if ($this->hasOverdueBudgets($patient)) {
            $badges[] = ['key' => 'delinquent', 'label' => 'Inadimplente', 'color' => 'red'];
        }

        if ($this->hasActiveTreatment($patient)) {
            $badges[] = ['key' => 'treatment_active', 'label' => 'Tratamento em andamento', 'color' => 'blue'];
        }

        if ($this->needsReturn($patient)) {
            $badges[] = ['key' => 'return_pending', 'label' => 'Retorno pendente', 'color' => 'amber'];
        }

        $ltv = $this->lifetimeValue($patient);
        if ($ltv >= self::HIGH_VALUE_THRESHOLD) {
            $badges[] = ['key' => 'high_value', 'label' => 'Alto valor', 'color' => 'purple'];
        }

        $lastVisit = $this->lastVisitDate($patient);
        if ($lastVisit && $lastVisit->lt(now()->subMonths(6))) {
            $badges[] = ['key' => 'inactive_6m', 'label' => 'Sem consulta há mais de 6 meses', 'color' => 'slate'];
        }

        if (count($this->clinicalAlerts($patient)) > 0) {
            $badges[] = ['key' => 'clinical_alert', 'label' => 'Possui alerta clínico', 'color' => 'orange'];
        }

        $birthday = $this->birthdayInfo($patient);
        if ($birthday['is_upcoming']) {
            $badges[] = [
                'key' => 'birthday',
                'label' => $birthday['days_until'] === 0 ? 'Aniversário hoje' : ($birthday['days_until'] === 1 ? 'Aniversário amanhã' : 'Aniversário próximo'),
                'color' => 'pink',
            ];
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

        return [
            'total_budgeted' => round($budgeted, 2),
            'total_received' => round($received, 2),
            'total_pending' => round($pending, 2),
            'ticket_average' => round($ticketAvg, 2),
            'lifetime_value' => round($this->lifetimeValue($patient), 2),
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

        return [
            'consultations_completed' => $completedConsultations,
            'treatments_completed' => $completedTreatments,
            'treatments_active' => $activeTreatments,
            'last_procedure' => $lastRecord?->procedure_name,
            'last_tooth' => $lastTooth,
            'last_visit_at' => $lastVisit?->toIso8601String(),
            'next_appointment_at' => $nextAppointment?->start?->toIso8601String(),
            'next_appointment_label' => $nextAppointment
                ? ($nextAppointment->treatment?->nome ?? 'Consulta')
                : null,
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
        $monthsAsPatient = $patientSince ? $patientSince->diffInMonths(now()) : 0;

        return [
            'attendance_rate' => $attendanceRate,
            'no_shows' => $noShows,
            'cancellations' => $cancelled,
            'months_as_patient' => $monthsAsPatient,
            'first_visit_at' => $firstVisit ? Carbon::parse($firstVisit)->toIso8601String() : null,
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

    public function timeline(Patient $patient): array
    {
        $events = collect();

        foreach ($patient->appointments as $apt) {
            $events->push([
                'type' => 'appointment_created',
                'category' => 'consultas',
                'occurred_at' => $apt->created_at->toIso8601String(),
                'title' => 'Consulta criada',
                'detail' => $apt->treatment?->nome,
                'meta' => ['appointment_id' => $apt->id, 'status' => $apt->status],
            ]);

            if ($apt->status === 'confirmed') {
                $events->push([
                    'type' => 'appointment_confirmed',
                    'category' => 'consultas',
                    'occurred_at' => $apt->updated_at->toIso8601String(),
                    'title' => 'Consulta confirmada',
                    'detail' => $apt->treatment?->nome,
                    'meta' => ['appointment_id' => $apt->id],
                ]);
            }

            if ($apt->status === 'cancelled') {
                $events->push([
                    'type' => 'appointment_cancelled',
                    'category' => 'consultas',
                    'occurred_at' => $apt->updated_at->toIso8601String(),
                    'title' => 'Consulta cancelada',
                    'detail' => $apt->treatment?->nome,
                    'meta' => ['appointment_id' => $apt->id],
                ]);
            }

            if ($apt->status === 'no_show') {
                $events->push([
                    'type' => 'appointment_no_show',
                    'category' => 'consultas',
                    'occurred_at' => $apt->start->toIso8601String(),
                    'title' => 'Falta registrada',
                    'detail' => $apt->treatment?->nome,
                    'meta' => ['appointment_id' => $apt->id],
                ]);
            }
        }

        foreach ($patient->consultations as $consultation) {
            if ($consultation->check_in_at) {
                $events->push([
                    'type' => 'check_in',
                    'category' => 'clinico',
                    'occurred_at' => $consultation->check_in_at->toIso8601String(),
                    'title' => 'Check-in realizado',
                    'detail' => $consultation->appointment?->treatment?->nome,
                    'meta' => ['consultation_id' => $consultation->id],
                ]);
            }

            if ($consultation->started_at) {
                $events->push([
                    'type' => 'attendance_started',
                    'category' => 'clinico',
                    'occurred_at' => $consultation->started_at->toIso8601String(),
                    'title' => 'Atendimento iniciado',
                    'detail' => $consultation->professional?->name,
                    'meta' => ['consultation_id' => $consultation->id],
                ]);
            }
        }

        foreach ($patient->clinicalRecords as $record) {
            if ($record->finished_at) {
                $events->push([
                    'type' => 'procedure_completed',
                    'category' => 'clinico',
                    'occurred_at' => $record->finished_at->toIso8601String(),
                    'title' => $record->procedure_name . ' concluído',
                    'detail' => $record->professional?->name,
                    'meta' => [
                        'record_id' => $record->id,
                        'price' => (float) $record->price,
                        'duration' => $record->duration_minutes,
                    ],
                ]);

                if ((float) $record->price > 0) {
                    $events->push([
                        'type' => 'payment_received',
                        'category' => 'financeiro',
                        'occurred_at' => $record->finished_at->copy()->addMinutes(2)->toIso8601String(),
                        'title' => 'Pagamento recebido',
                        'detail' => 'R$ ' . number_format((float) $record->price, 2, ',', '.'),
                        'meta' => ['amount' => (float) $record->price, 'method' => 'Consultório'],
                    ]);
                }
            }
        }

        foreach ($patient->budgets as $budget) {
            $events->push([
                'type' => 'budget_' . $budget->status,
                'category' => 'financeiro',
                'occurred_at' => $budget->created_at->toIso8601String(),
                'title' => 'Orçamento ' . $budget->status,
                'detail' => 'R$ ' . number_format((float) $budget->total, 2, ',', '.'),
                'meta' => ['budget_id' => $budget->id],
            ]);
        }

        foreach ($patient->evolutions as $evolution) {
            $events->push([
                'type' => 'evolution',
                'category' => 'clinico',
                'occurred_at' => $evolution->recorded_at->toIso8601String(),
                'title' => 'Evolução registrada',
                'detail' => $evolution->professional?->name,
                'meta' => ['evolution_id' => $evolution->id, 'preview' => mb_substr($evolution->content, 0, 120)],
            ]);
        }

        foreach ($patient->photos as $photo) {
            $category = $photo->categoria === 'Documentação' ? 'documentos' : 'arquivos';
            $events->push([
                'type' => 'file_added',
                'category' => $category,
                'occurred_at' => ($photo->taken_at ?? $photo->created_at)->toIso8601String(),
                'title' => $category === 'documentos' ? 'Documento adicionado' : 'Arquivo clínico adicionado',
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