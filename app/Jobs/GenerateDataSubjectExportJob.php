<?php

namespace App\Jobs;

use App\Models\DataSubjectExport;
use App\Services\Admin\DataSubjectExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Monta o ZIP completo de um paciente (dezenas de categorias + PDFs do S3 +
 * fotos do Google Drive por arquivo) e sobe pro S3 — roda no worker, nunca
 * na request HTTP, mesmo padrão de GenerateAndSendDocumentShareJob (Fase
 * B5): evita timeout de ALB/PHP-FPM e mantém o resultado em storage
 * compartilhado entre os containers web/worker do Fargate (disco local do
 * worker não é visível pelo container que serve o download).
 *
 * Idempotência: DataSubjectExportService::buildPatientExportZip() é um
 * no-op se generation_status já é 'ready'.
 *
 * Retry: falhas aqui tendem a ser transitórias (S3, API do Google Drive) —
 * tries=3 com backoff, igual a GenerateAndSendDocumentShareJob.
 */
class GenerateDataSubjectExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $exportId) {}

    public function handle(DataSubjectExportService $service): void
    {
        $export = DataSubjectExport::find($this->exportId);

        if (! $export || $export->generation_status === DataSubjectExport::GENERATION_READY) {
            return;
        }

        try {
            $service->buildPatientExportZip($export);
        } catch (\Throwable $e) {
            Log::error('[GenerateDataSubjectExportJob] Falha ao montar exportação de titular', [
                'export_id' => $this->exportId,
                'error'     => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Rede de segurança: se as 3 tentativas se esgotarem, o registro não
     * pode ficar preso em "processing" pra sempre — a UI precisa poder
     * mostrar "Falhou" em vez de fazer polling pra sempre.
     */
    public function failed(\Throwable $exception): void
    {
        $export = DataSubjectExport::find($this->exportId);

        if ($export && $export->generation_status !== DataSubjectExport::GENERATION_READY) {
            $export->update([
                'generation_status'        => DataSubjectExport::GENERATION_FAILED,
                'generation_failed_reason' => $exception->getMessage(),
            ]);
        }

        Log::error('[GenerateDataSubjectExportJob] Falha definitiva após todas as tentativas', [
            'export_id' => $this->exportId,
            'error'     => $exception->getMessage(),
        ]);
    }
}
