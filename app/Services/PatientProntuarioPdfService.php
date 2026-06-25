<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientOdontogram;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class PatientProntuarioPdfService
{
    public function generate(Patient $patient): string
    {
        $patient->load([
            'anamnesis',
            'odontogram',
            'evolutions.professional',
            'clinicalRecords.professional',
            'photos',
            'clinic',
        ]);

        $clinic = $patient->clinic;
        $logoDataUri = null;

        if ($clinic?->logo_path && Storage::disk('public')->exists($clinic->logo_path)) {
            $contents = Storage::disk('public')->get($clinic->logo_path);
            $mime = Storage::disk('public')->mimeType($clinic->logo_path) ?: 'image/png';
            $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode($contents);
        }

        $html = View::make('pdf.patient-prontuario', [
            'patient' => $patient,
            'clinic' => $clinic,
            'logoDataUri' => $logoDataUri,
            'toothStatuses' => PatientOdontogram::TOOTH_STATUSES,
            'fdiTeeth' => PatientOdontogram::FDI_TEETH,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        $filename = 'prontuarios/patient-' . $patient->id . '.pdf';
        Storage::disk('public')->put($filename, $dompdf->output());

        return $filename;
    }
}