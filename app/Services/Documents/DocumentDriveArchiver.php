<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Storage;

/**
 * Arquiva (best-effort) o PDF final de um Document no Google Drive do paciente,
 * reaproveitando GoogleDriveService::uploadPhoto() sem alterar esse serviço
 * compartilhado. Nunca deve bloquear a emissão do documento — qualquer falha
 * (clínica sem Drive conectado, paciente sem pasta ainda, etc.) é silenciosa.
 */
class DocumentDriveArchiver
{
    public function __construct(private GoogleDriveService $drive) {}

    public function archive(Document $document): void
    {
        try {
            $clinic = $document->clinic;
            $patient = $document->patient;

            if (! $clinic || ! $patient) {
                return;
            }

            $connection = $clinic->storageConnection;
            if (! $connection || $connection->status !== 'active') {
                return;
            }

            $doctor = $document->professional ?? $clinic->owner();
            if (! $doctor) {
                return;
            }

            // PDF vive no disco S3 privado agora — GoogleDriveService::uploadPhoto()
            // espera um caminho de arquivo LOCAL (usado pelo cliente da API do
            // Google para multipart upload), então baixamos para um temporário
            // e limpamos logo em seguida, sem alterar uploadPhoto() em si.
            if (! $document->pdf_path || ! Storage::disk('s3')->exists($document->pdf_path)) {
                return;
            }

            $tmpPath = tempnam(sys_get_temp_dir(), 'doc-drive-archive-') . '.pdf';
            file_put_contents($tmpPath, Storage::disk('s3')->get($document->pdf_path));

            try {
                $this->drive->uploadPhoto(
                    $patient,
                    $doctor,
                    $tmpPath,
                    $document->template_name . ' - ' . $document->document_code . '.pdf',
                    'application/pdf',
                    [
                        'categoria'    => 'Documentação',
                        'subcategoria' => $document->template_name,
                    ]
                );
            } finally {
                @unlink($tmpPath);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
