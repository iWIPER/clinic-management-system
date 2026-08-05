<?php

namespace App\Mail;

use App\Models\PatientInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PatientInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly PatientInvite $invite, public readonly string $url) {}

    public function envelope(): Envelope
    {
        $clinicName = $this->invite->clinic?->trade_name ?? $this->invite->clinic?->name ?? 'ClinicFlow';

        return new Envelope(
            subject: $this->invite->kind === 'atualizacao'
                ? "Atualize seu cadastro na {$clinicName}"
                : "Complete seu cadastro na {$clinicName}",
        );
    }

    public function content(): Content
    {
        $clinic     = $this->invite->clinic;
        $patient    = $this->invite->patient;
        $clinicName = $clinic?->trade_name ?? $clinic?->name ?? 'ClinicFlow';

        $clinicLogo = null;
        if ($clinic) {
            try {
                $clinicLogo = \App\Services\ClinicLogoService::dataUri($clinic);
            } catch (\Throwable) {
                // logo indisponível — email ainda funciona sem ela
            }
        }

        return new Content(
            view: 'emails.patient-invite',
            with: [
                'invite'     => $this->invite,
                'patient'    => $patient,
                'url'        => $this->url,
                'clinicName' => $clinicName,
                'clinicLogo' => $clinicLogo,
                'isUpdate'   => $this->invite->kind === 'atualizacao',
                'expiresAt'  => $this->invite->expires_at?->format('d/m/Y \à\s H:i'),
            ],
        );
    }
}
