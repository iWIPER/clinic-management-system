<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientOdontogram;
use App\Services\ClinicLogoService;
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
        $logoDataUri = $clinic ? ClinicLogoService::dataUri($clinic) : null;

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
        // Sem visibilidade: bucket usa Object Ownership "BucketOwnerEnforced",
        // que recusa ACL por objeto — privacidade vem do Block Public Access.
        Storage::disk('s3')->put($filename, $dompdf->output());

        return $filename;
    }
}