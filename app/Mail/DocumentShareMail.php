<?php

namespace App\Mail;

use App\Models\DocumentShare;
use App\Services\ClinicLogoService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail inicial do compartilhamento de documento — nunca contém a senha
 * (ver DocumentSharePasswordMail/DocumentSharePasswordController). Anexa o
 * PDF protegido por senha quando cabe no limite de anexo; acima disso, só
 * o link de visualização/senha.
 */
class DocumentShareMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly DocumentShare $share,
        public readonly string $revealUrl,
        public readonly ?string $attachmentBytes = null,
    ) {}

    public function envelope(): Envelope
    {
        $clinicName = $this->share->patient->clinic?->displayName() ?? 'Wildental';

        return new Envelope(
            subject: "{$this->share->friendly_filename} — {$clinicName}",
        );
    }

    public function content(): Content
    {
        $clinic = $this->share->patient->clinic;
        $clinicLogo = null;

        if ($clinic) {
            try {
                $clinicLogo = ClinicLogoService::dataUri($clinic);
            } catch (\Throwable) {
                // logo indisponível — email ainda funciona sem ela
            }
        }

        return new Content(
            view: 'emails.document-share',
            with: [
                'share'       => $this->share,
                'revealUrl'   => $this->revealUrl,
                'clinicName'  => $clinic?->displayName() ?? 'Wildental',
                'clinicLogo'  => $clinicLogo,
                'hasAttachment' => $this->attachmentBytes !== null,
                'expiresAt'   => $this->share->expires_at->format('d/m/Y \à\s H:i'),
            ],
        );
    }

    public function attachments(): array
    {
        if ($this->attachmentBytes === null) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->attachmentBytes, $this->share->friendly_filename)
                ->withMime('application/pdf'),
        ];
    }
}
