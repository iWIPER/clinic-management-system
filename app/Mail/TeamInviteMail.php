<?php

namespace App\Mail;

use App\Models\Invite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Invite $invite) {}

    public function envelope(): Envelope
    {
        $clinicName = $this->invite->clinic?->trade_name ?? $this->invite->clinic?->name ?? 'Wildental';

        return new Envelope(
            subject: "Você foi convidado para {$clinicName} no Wildental",
        );
    }

    public function content(): Content
    {
        $clinic     = $this->invite->clinic;
        $acceptUrl  = config('app.url') . '/convites/' . $this->invite->short_token;
        $clinicName = $clinic?->trade_name ?? $clinic?->name ?? 'Wildental';
        $daysLeft   = max(0, (int) now()->diffInDays($this->invite->expires_at, false));

        $clinicLogo = null;
        if ($clinic) {
            try {
                $clinicLogo = \App\Services\ClinicLogoService::dataUri($clinic);
            } catch (\Throwable) {
                // logo indisponível — email ainda funciona sem ela
            }
        }

        return new Content(
            view: 'emails.team-invite',
            with: [
                'invite'     => $this->invite,
                'acceptUrl'  => $acceptUrl,
                'clinicName' => $clinicName,
                'clinicLogo' => $clinicLogo,
                'invitedBy'  => $this->invite->invitedBy?->name ?? 'Administrador',
                'expiresAt'  => $this->invite->expires_at->format('d/m/Y \à\s H:i'),
                'daysLeft'   => $daysLeft,
            ],
        );
    }
}
