<?php

namespace App\Services;

use App\Models\ClinicalRecord;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class ClinicalRecordPdfService
{
    public function generate(ClinicalRecord $record): string
    {
        $record->load(['patient', 'professional', 'clinic']);

        $clinic = $record->clinic;
        $logoDataUri = null;

        if ($clinic?->logo_path && Storage::disk('public')->exists($clinic->logo_path)) {
            $contents = Storage::disk('public')->get($clinic->logo_path);
            $mime = Storage::disk('public')->mimeType($clinic->logo_path) ?: 'image/png';
            $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode($contents);
        }

        $html = View::make('pdf.clinical-record', [
            'record' => $record,
            'clinic' => $clinic,
            'logoDataUri' => $logoDataUri,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        $filename = 'clinical-records/record-' . $record->id . '.pdf';
        Storage::disk('public')->put($filename, $dompdf->output());

        $record->update(['pdf_path' => $filename]);

        return $filename;
    }
}