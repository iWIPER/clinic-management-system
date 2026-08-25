<?php

namespace App\Http\Controllers;

use App\Models\ClinicalRecord;
use App\Services\ClinicalRecordPdfService;
use Illuminate\Support\Facades\Storage;

/**
 * Geração de PDF de um ClinicalRecord (recibo de procedimento) — a listagem
 * e o detalhe de "Atendimentos" saíram daqui para AttendanceController
 * (Appointment é a fonte agora; ClinicalRecord não é mais exigido para uma
 * consulta aparecer no histórico). Este controller continua existindo só
 * para quem realmente usa ClinicalRecord (Financeiro/Pagamentos/PatientHubService
 * consultam o model direto, não passam por aqui).
 */
class ClinicalRecordController extends Controller
{
    public function generatePdf(ClinicalRecord $clinicalRecord, ClinicalRecordPdfService $pdfService)
    {
        $this->authorize('view', $clinicalRecord);

        $path = $pdfService->generate($clinicalRecord);

        return Storage::disk('s3')->download($path, 'atendimento-' . $clinicalRecord->id . '.pdf');
    }
}
