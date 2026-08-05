<?php

namespace App\Mail;

use App\Models\Document;
use App\Services\ClinicLogoService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentSignatureRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Document $document, public readonly string $signUrl) {}

    public function envelope(): Envelope
    {
        $clinicName = $this->document->clinic?->displayName() ?? 'ClinicFlow';

        return new Envelope(
            subject: "Assinatura pendente — {$this->document->template_name} ({$clinicName})",
        );
    }

    public function content(): Content
    {
        $clinic = $this->document->clinic;
        $clinicLogo = null;

        if ($clinic) {
            try {
                $clinicLogo = ClinicLogoService::dataUri($clinic);
            } catch (\Throwable) {
                // logo indisponível — email ainda funciona sem ela
            }
        }

        return new Content(
            view: 'emails.document-signature-request',
            with: [
                'document'    => $this->document,
                'signUrl'     => $this->signUrl,
                'clinicName'  => $clinic?->displayName() ?? 'ClinicFlow',
                'clinicLogo'  => $clinicLogo,
                'patientName' => $this->document->patient?->nome_completo,
                'expiresAt'   => $this->document->signature_token_expires_at?->format('d/m/Y \à\s H:i'),
            ],
        );
    }
}
