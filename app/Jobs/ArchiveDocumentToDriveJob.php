<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Documents\DocumentDriveArchiver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Move DocumentDriveArchiver::archive() (upload best-effort do PDF final pro
 * Google Drive do paciente) pra fora da requisição que emite o documento —
 * medido: soma download do S3 + upload multipart pro Drive antes mesmo de
 * poder devolver o PDF pra quem pediu.
 *
 * archive() já é 100% best-effort por design (qualquer falha é silenciosa,
 * via report($e) — ver comentário na própria classe: "Nunca deve bloquear a
 * emissão do documento"), então mover pra um job não muda nenhum
 * comportamento observável, só quando roda. Por isso tries=1: não existe
 * idempotência garantida no upload do Drive (sem chave de deduplicação —
 * ver DocumentDriveArchiver), então tentar de novo automaticamente arrisca
 * duplicar o arquivo lá; igual à decisão já tomada em UploadEvolutionPhotoJob
 * pelo mesmo motivo.
 */
class ArchiveDocumentToDriveJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $documentId) {}

    public function handle(DocumentDriveArchiver $archiver): void
    {
        $document = Document::find($this->documentId);

        if (! $document) {
            return;
        }

        $archiver->archive($document);
    }
}
