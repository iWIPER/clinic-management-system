<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Services\GoogleDriveService;

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

            $localPath = storage_path('app/public/' . $document->pdf_path);
            if (! $document->pdf_path || ! file_exists($localPath)) {
                return;
            }

            $this->drive->uploadPhoto(
                $patient,
                $doctor,
                $localPath,
                $document->template_name . ' - ' . $document->document_code . '.pdf',
                'application/pdf',
                [
                    'categoria'    => 'Documentação',
                    'subcategoria' => $document->template_name,
                ]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
