<?php

namespace App\Mail;

use App\Models\DocumentShare;
use App\Services\ClinicLogoService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail separado, disparado só quando o destinatário (já com identidade
 * verificada) escolhe "Enviar senha por e-mail" na página de revelação —
 * nunca é o mesmo e-mail que leva o documento (ver DocumentShareMail).
 */
class DocumentSharePasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly DocumentShare $share,
        public readonly string $composedMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Senha do arquivo {$this->share->friendly_filename}",
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
                //
            }
        }

        return new Content(
            view: 'emails.document-share-password',
            with: [
                'share'           => $this->share,
                'composedMessage' => $this->composedMessage,
                'clinicName'      => $clinic?->displayName() ?? 'Wildental',
                'clinicLogo'      => $clinicLogo,
            ],
        );
    }
}
