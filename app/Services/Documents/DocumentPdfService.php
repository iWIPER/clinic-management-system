<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentActivityLog;
use App\Services\ClinicLogoService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class DocumentPdfService
{
    public function __construct(private DocumentPlaceholderResolver $resolver, private DocumentDriveArchiver $archiver) {}

    public function generate(Document $document, ?int $userId = null, $request = null): string
    {
        $document->load(['patient', 'professional', 'clinic', 'signatures', 'template']);

        if (! $document->validation_token) {
            $document->update(['validation_token' => bin2hex(random_bytes(32))]);
            $document->refresh();
        }

        $verifyUrl = route('documents.validate', ['token' => $document->validation_token]);
        $qrDataUri = $this->buildQrDataUri($verifyUrl);
        $logoDataUri = $document->clinic ? ClinicLogoService::dataUri($document->clinic) : null;

        $contentHtml = $this->resolver->resolveSignatureBlocksForPdf($document->rendered_html, $document->signatures);

        $html = View::make('pdf.document', [
            'document'    => $document,
            'clinic'      => $document->clinic,
            'patient'     => $document->patient,
            'logoDataUri' => $logoDataUri,
            'qrDataUri'   => $qrDataUri,
            'contentHtml' => $contentHtml,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $canvas->page_text(500, 818, 'Página {PAGE_NUM} de {PAGE_COUNT}', null, 7, [0.6, 0.6, 0.6]);

        $filename = 'documents/document-' . $document->id . '.pdf';
        Storage::disk('public')->put($filename, $dompdf->output());

        $document->update([
            'pdf_path'     => $filename,
            'content_hash' => hash('sha256', $document->rendered_html),
        ]);

        DocumentActivityLog::create([
            'clinic_id'   => $document->clinic_id,
            'document_id' => $document->id,
            'patient_id'  => $document->patient_id,
            'document_template_id' => $document->template_id,
            'action'      => 'pdf_generated',
            'user_id'     => $userId,
            'ip_address'  => $request?->ip(),
            'user_agent'  => $request?->userAgent(),
        ]);

        $this->archiver->archive($document->fresh());

        return $filename;
    }

    private function buildQrDataUri(string $url): ?string
    {
        if (! class_exists(\BaconQrCode\Writer::class)) {
            return null;
        }

        try {
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(120),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            );
            $writer = new \BaconQrCode\Writer($renderer);
            $svg = $writer->writeString($url);

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable) {
            return null;
        }
    }
}
