<?php

namespace App\Services\Documents;

use App\Mail\DocumentSharePasswordMail;
use App\Models\DocumentShare;
use Illuminate\Support\Facades\Mail;

/**
 * Envio da senha do documento compartilhado por canal, após identidade
 * verificada. E-mail reusa a infraestrutura de Mail já existente no
 * projeto (mesmo padrão de TeamInviteMail/DocumentSignatureRequestMail).
 *
 * WhatsApp/SMS: o projeto já tem o precedente de "canal preparado, sem
 * provider conectado" (ver Patient::canal_lembrete, cujo comentário no
 * model diz literalmente "só armazena, sem envio implementado"). Seguimos
 * o mesmo padrão aqui — não instalamos nenhum SaaS de WhatsApp/SMS; a
 * mensagem pronta é composta e devolvida para o frontend oferecer copiar/
 * compartilhar, e o canal fica pronto para um adapter real no futuro.
 */
class PasswordDeliveryService
{
    private const AVAILABLE_CHANNELS = ['email', 'whatsapp', 'sms'];

    public function send(DocumentShare $share, string $channel, string $plainPassword): array
    {
        if (! in_array($channel, self::AVAILABLE_CHANNELS, true)) {
            throw new \InvalidArgumentException('Canal de envio inválido.');
        }

        return match ($channel) {
            'email' => $this->sendEmail($share, $plainPassword),
            default => $this->notConfigured($share, $plainPassword, $channel),
        };
    }

    public function composeMessage(DocumentShare $share, string $plainPassword): string
    {
        $recipient = $share->recipient_name ?: 'tudo bem';

        return "Olá, {$recipient}.\n\n"
            . "Aqui está a senha do arquivo\n"
            . "'{$share->friendly_filename}':\n\n"
            . $plainPassword;
    }

    private function sendEmail(DocumentShare $share, string $plainPassword): array
    {
        // Envio SÍNCRONO, de propósito: com QUEUE_CONNECTION=database, um
        // Mailable enfileirado carregando a senha em texto puro ficaria
        // serializado (não criptografado) na tabela jobs até ser processado.
        // Este e-mail é curto e sem anexo, então o custo de latência é baixo
        // — diferente do e-mail principal do documento (DocumentShareMail),
        // que pode ter anexo grande e por isso é enfileirado.
        Mail::to($share->recipient_email)->send(
            new DocumentSharePasswordMail($share, $this->composeMessage($share, $plainPassword))
        );

        return ['status' => 'sent', 'channel' => 'email'];
    }

    private function notConfigured(DocumentShare $share, string $plainPassword, string $channel): array
    {
        return [
            'status'  => 'not_configured',
            'channel' => $channel,
            'message' => $this->composeMessage($share, $plainPassword),
        ];
    }
}
