<?php

namespace App\Console\Commands;

use App\Enums\Documents\DocumentStatus;
use App\Models\Document;
use App\Services\Documents\DocumentStatusService;
use Illuminate\Console\Command;

class ExpireDocumentSignatureTokens extends Command
{
    protected $signature = 'documents:expire-signature-tokens';

    protected $description = 'Expira links públicos de assinatura de documentos vencidos';

    public function handle(DocumentStatusService $statusService): int
    {
        $documents = Document::query()
            ->whereNotNull('signature_token_expires_at')
            ->where('signature_token_expires_at', '<', now())
            ->whereNotIn('status', [DocumentStatus::Completed->value, DocumentStatus::Cancelled->value, DocumentStatus::Expired->value])
            ->get();

        foreach ($documents as $document) {
            $statusService->markExpired($document);
        }

        $this->info("Expirados: {$documents->count()} documento(s).");

        return self::SUCCESS;
    }
}
