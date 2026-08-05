<?php

namespace App\Services\Anamnesis;

use App\Models\AnamnesisActivityLog;
use App\Models\AnamnesisInstance;
use App\Services\ClinicLogoService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class AnamnesisPdfService
{
    public function __construct(private AnamnesisService $anamnesisService) {}

    public function generate(AnamnesisInstance $instance, int $userId, $request = null): string
    {
        $instance->load(['patient', 'professional', 'clinic', 'answers', 'patientSignature', 'dentistSignature']);
        $editor = $this->anamnesisService->loadEditorData($instance);

        $clinic = $instance->clinic;
        $logoDataUri = $clinic ? ClinicLogoService::dataUri($clinic) : null;

        if (! $instance->validation_token) {
            $instance->update(['validation_token' => bin2hex(random_bytes(32))]);
            $instance->refresh();
        }

        $verifyUrl = route('anamneses.validate', ['token' => $instance->validation_token]);

        $qrDataUri = $this->buildQrDataUri($verifyUrl);

        $patientSig  = $instance->patientSignature;
        $dentistSig  = $instance->dentistSignature;

        $patientSignatureDataUri = $this->sigDataUri($patientSig?->signature_path);
        $dentistSignatureDataUri = $this->sigDataUri($dentistSig?->signature_path);

        $html = View::make('pdf.anamnesis', [
            'instance'               => $instance,
            'categories'             => $editor['categories'],
            'clinic'                 => $clinic,
            'logoDataUri'            => $logoDataUri,
            'qrDataUri'              => $qrDataUri,
            'signatureDataUri'       => $patientSignatureDataUri,
            'patientSignature'       => $patientSig,
            'dentistSignature'       => $dentistSig,
            'patientSignatureDataUri' => $patientSignatureDataUri,
            'dentistSignatureDataUri' => $dentistSignatureDataUri,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        $filename = 'anamneses/instance-' . $instance->id . '.pdf';
        Storage::disk('public')->put($filename, $dompdf->output());

        $instance->update(['pdf_path' => $filename]);

        AnamnesisActivityLog::create([
            'clinic_id' => $instance->clinic_id,
            'instance_id' => $instance->id,
            'patient_id' => $instance->patient_id,
            'template_id' => $instance->template_id,
            'action' => 'pdf_generated',
            'user_id' => $userId,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);

        return $filename;
    }

    private function sigDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }
        $binary = Storage::disk('public')->get($path);
        return 'data:image/png;base64,' . base64_encode($binary);
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