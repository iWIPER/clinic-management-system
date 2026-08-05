<?php

namespace App\Services\Documents;

use App\Enums\Documents\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentActivityLog;
use App\Models\DocumentSignature;

/**
 * Centraliza as transições de status de um Document. Ao contrário da Anamnese
 * (ordem fixa paciente -> profissional), aqui a obrigatoriedade de cada papel
 * vem das flags do template (requires_*_signature), sem ordem rígida.
 */
class DocumentStatusService
{
    public function advanceAfterSignature(Document $document, string $signerRole): void
    {
        $document->refresh();

        if ($document->isFullySigned()) {
            $document->update(['status' => DocumentStatus::Completed->value, 'completed_at' => now()]);

            return;
        }

        $status = $signerRole === DocumentSignature::ROLE_PATIENT
            ? DocumentStatus::PatientSigned
            : DocumentStatus::ProfessionalSigned;

        $document->update(['status' => $status->value]);
    }

    public function cancel(Document $document, string $reason, ?int $userId): void
    {
        $document->update([
            'status'        => DocumentStatus::Cancelled->value,
            'cancelled_at'  => now(),
            'cancel_reason' => $reason,
            'signature_token' => null,
            'signature_token_expires_at' => null,
        ]);

        DocumentActivityLog::create([
            'clinic_id'   => $document->clinic_id,
            'document_id' => $document->id,
            'patient_id'  => $document->patient_id,
            'document_template_id' => $document->template_id,
            'action'      => 'cancelled',
            'user_id'     => $userId,
            'metadata'    => ['reason' => $reason],
        ]);
    }

    public function markExpired(Document $document): void
    {
        $document->update([
            'status'           => DocumentStatus::Expired->value,
            'signature_token'  => null,
        ]);

        DocumentActivityLog::create([
            'clinic_id'   => $document->clinic_id,
            'document_id' => $document->id,
            'patient_id'  => $document->patient_id,
            'document_template_id' => $document->template_id,
            'action'      => 'expired',
        ]);
    }
}
