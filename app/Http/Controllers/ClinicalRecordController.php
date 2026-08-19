<?php

namespace App\Http\Controllers;

use App\Enums\ClinicalRecordStatus;
use App\Models\ClinicalRecord;
use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Models\ProcedureExecution;
use App\Models\User;
use App\Services\ClinicalRecordPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ClinicalRecordController extends Controller
{
    public function index(Request $request)
    {
        $query = ClinicalRecord::query()
            ->with(['patient:id,nome,sobrenome', 'professional:id,name'])
            ->orderByDesc('finished_at');

        if ($patientId = $request->input('patient_id')) {
            $query->where('patient_id', $patientId);
        }

        if ($professionalId = $request->input('professional_id')) {
            $query->where('professional_id', $professionalId);
        }

        if ($procedure = $request->input('procedure')) {
            $query->where('procedure_name', 'like', "%{$procedure}%");
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('finished_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('finished_at', '<=', $to);
        }

        $records = $query->paginate(15)->withQueryString();

        $clinicId = session('current_clinic_id');

        return Inertia::render('ClinicalRecords/Index', [
            'records' => $records,
            'filters' => $request->only(['patient_id', 'professional_id', 'procedure', 'status', 'from', 'to']),
            'patients' => Patient::select('id', 'nome', 'sobrenome')->orderBy('nome')->get(),
            'professionals' => User::whereHas('clinics', fn ($q) => $q->where('clinics.id', $clinicId))
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'statuses' => collect(ClinicalRecordStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function show(ClinicalRecord $clinicalRecord)
    {
        $this->authorize('view', $clinicalRecord);

        $clinicalRecord->load([
            'patient',
            'professional',
            'appointment.treatment',
            'consultation.procedureExecutions.treatment',
        ]);

        $photos = PatientPhoto::where('patient_id', $clinicalRecord->patient_id)
            ->when($clinicalRecord->started_at && $clinicalRecord->finished_at, function ($q) use ($clinicalRecord) {
                $q->whereBetween('taken_at', [
                    $clinicalRecord->started_at->copy()->startOfDay(),
                    $clinicalRecord->finished_at->copy()->endOfDay(),
                ]);
            })
            ->orderByDesc('taken_at')
            ->get();

        $attachments = $clinicalRecord->consultation
            ? ProcedureExecution::where('consultation_id', $clinicalRecord->consultation_id)
                ->with('treatment:id,nome')
                ->get()
            : collect();

        return Inertia::render('ClinicalRecords/Show', [
            'record' => $clinicalRecord,
            'photos' => $photos,
            'attachments' => $attachments,
        ]);
    }

    public function generatePdf(ClinicalRecord $clinicalRecord, ClinicalRecordPdfService $pdfService)
    {
        $this->authorize('view', $clinicalRecord);

        $path = $pdfService->generate($clinicalRecord);

        return Storage::disk('s3')->download($path, 'atendimento-' . $clinicalRecord->id . '.pdf');
    }
}