<?php

namespace App\Jobs;

use App\Models\DocumentShare;
use App\Services\Documents\DocumentShareService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Gera o PDF protegido por senha, sobe pro S3 e envia o e-mail de um
 * compartilhamento de documento já registrado (DocumentShareService::share()
 * já criou a linha antes de despachar este job). Recebe só o ID — a senha
 * nunca passa pelo construtor/payload serializado deste job: é relida do
 * próprio DocumentShare (cast 'encrypted') dentro de handle(), o mesmo
 * mecanismo que PasswordDeliveryService já usa. Isso evita o texto puro da
 * senha aparecer na tabela `jobs`.
 *
 * Idempotência: DocumentShareService::generateAndSend() é um no-op se
 * generation_status já é 'sent' — então um redispatch acidental ou um retry
 * após timeout não gera um segundo e-mail nem uma segunda cobrança de
 * "documento enviado" na auditoria.
 *
 * Retry: falhas aqui tendem a ser transitórias (S3, SMTP) — tries=3 com
 * backoff, igual a SubmitFinancingProposalJob (mesmo padrão já usado neste
 * projeto pra chamada externa que vale a pena tentar de novo).
 */
class GenerateAndSendDocumentShareJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $shareId) {}

    public function handle(DocumentShareService $service): void
    {
        $share = DocumentShare::find($this->shareId);

        if (! $share || $share->generation_status === DocumentShare::GENERATION_SENT) {
            return;
        }

        try {
            $service->generateAndSend($share);
        } catch (\Throwable $e) {
            Log::error('[GenerateAndSendDocumentShareJob] Falha ao gerar/enviar compartilhamento', [
                'share_id' => $this->shareId,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Rede de segurança: se as 3 tentativas se esgotarem, o compartilhamento
     * não pode ficar preso em "processing" pra sempre — a UI (painel de
     * compartilhamentos) precisa poder mostrar "Falhou" e permitir que a
     * clínica tente de novo (gera um novo DocumentShare, não reaproveita
     * este — mesma política de token/senha novos a cada tentativa).
     */
    public function failed(\Throwable $exception): void
    {
        $share = DocumentShare::find($this->shareId);

        if ($share && $share->generation_status !== DocumentShare::GENERATION_SENT) {
            $share->update(['generation_status' => DocumentShare::GENERATION_FAILED]);
        }

        Log::error('[GenerateAndSendDocumentShareJob] Falha definitiva após todas as tentativas', [
            'share_id' => $this->shareId,
            'error'    => $exception->getMessage(),
        ]);
    }
}
