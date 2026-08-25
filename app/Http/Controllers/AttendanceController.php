<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Chair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * "Atendimentos" — histórico/listagem dos agendamentos (Appointment) da
 * clínica, não uma tela de procedimentos. Antes desta separação, essas
 * mesmas rotas (clinical-records.index/.show) eram servidas por
 * ClinicalRecordController e mostravam ClinicalRecord (procedimento +
 * valor) — ClinicalRecord continua existindo normalmente para quem
 * realmente precisa dele (Financeiro, Pagamentos, PatientHubService, PDF
 * de procedimento em ClinicalRecordController::generatePdf()), só deixou
 * de ser a fonte desta tela. Uma consulta (Appointment) NUNCA exige um
 * ClinicalRecord para aparecer aqui — os dois conceitos não se misturam.
 */
class AttendanceController extends Controller
{
    /**
     * Mesma lista de PatientController::PER_PAGE_OPTIONS — único padrão de
     * paginação do projeto, o <select> do front nunca oferece um valor que
     * o backend recusaria.
     */
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public function index(Request $request)
    {
        $clinicId = session('current_clinic_id');

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 10;
        }

        $appointments = $this->filteredQuery($request)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Attendances/Index', [
            'appointments' => [
                'data' => $appointments->items(),
                'pagination' => [
                    'current_page' => $appointments->currentPage(),
                    'last_page'    => $appointments->lastPage(),
                    'total'        => $appointments->total(),
                    'per_page'     => $appointments->perPage(),
                ],
            ],
            'filters' => [
                ...$request->only(['patient_id', 'professional_id', 'chair_id', 'status', 'from', 'to']),
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'patients' => Patient::select('id', 'nome', 'sobrenome')->orderBy('nome')->get(),
            'professionals' => User::whereHas('clinics', fn ($q) => $q->where('clinics.id', $clinicId))
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'chairs' => Chair::where('clinic_id', $clinicId)->orderBy('name')->get(['id', 'name', 'color']),
            // Mesmos 6 status reais de Appointment (ver AppointmentController::
            // updateStatus()) — nenhum status novo/paralelo criado pra esta tela.
            'statuses' => [
                ['value' => 'scheduled', 'label' => 'Agendada'],
                ['value' => 'confirmed', 'label' => 'Confirmada'],
                ['value' => 'in_attendance', 'label' => 'Em atendimento'],
                ['value' => 'completed', 'label' => 'Concluída'],
                ['value' => 'no_show', 'label' => 'Faltou'],
                ['value' => 'cancelled', 'label' => 'Cancelada'],
            ],
        ]);
    }

    public function show(Appointment $appointment)
    {
        $appointment->load(['patient', 'professional', 'chair', 'tags', 'appointmentReturn']);

        return Inertia::render('Attendances/Show', [
            'appointment' => $appointment,
        ]);
    }

    /**
     * Exporta exatamente os atendimentos que a listagem exibiria com os
     * mesmos filtros (mesma filteredQuery() de index()) — mesmo princípio
     * já usado por PatientController::export(). Sem procedimento/valor: só
     * os campos que pertencem de fato ao registro do atendimento.
     */
    public function export(Request $request)
    {
        $appointments = $this->filteredQuery($request)->get();

        $filename = 'atendimentos-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($appointments) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($handle, ['Data', 'Horário', 'Paciente', 'Profissional', 'Cadeira', 'Status', 'Observação']);

            foreach ($appointments as $appt) {
                fputcsv($handle, [
                    $appt->start->format('d/m/Y'),
                    $appt->start->format('H:i') . '–' . $appt->end->format('H:i'),
                    trim($appt->patient->nome . ' ' . $appt->patient->sobrenome),
                    $appt->professional?->name ?? '',
                    $appt->chair?->name ?? '',
                    $this->statusLabel($appt->status),
                    $appt->notes ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function statusLabel(string $status): string
    {
        return [
            'scheduled' => 'Agendada',
            'confirmed' => 'Confirmada',
            'in_attendance' => 'Em atendimento',
            'completed' => 'Concluída',
            'no_show' => 'Faltou',
            'cancelled' => 'Cancelada',
        ][$status] ?? $status;
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = Appointment::query()
            ->with(['patient:id,nome,sobrenome', 'professional:id,name', 'chair:id,name,color'])
            ->orderByDesc('start');

        if ($patientId = $request->input('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        if ($professionalId = $request->input('professional_id')) {
            $query->where('professional_id', $professionalId);
        }

        if ($chairId = $request->input('chair_id')) {
            $query->where('chair_id', $chairId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('start', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('start', '<=', $to);
        }

        return $query;
    }
}
