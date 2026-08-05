<?php

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentSignatureProviderInterface;
use App\Models\Document;
use App\Models\DocumentSignature;
use Illuminate\Support\Facades\Storage;

/**
 * Provedor local de assinatura eletrônica via canvas para o módulo Documentos.
 * Espelha deliberadamente App\Services\Signature\LocalSignatureProvider (mesmo
 * hashing/storage), mas é uma classe paralela e independente, genérica para os
 * 4 papéis de assinante — não importa nem modifica nada do namespace Anamnesis.
 */
class LocalDocumentSignatureProvider implements DocumentSignatureProviderInterface
{
    public function __construct(private DocumentStatusService $statusService) {}

    public function sign(Document $document, string $signerRole, array $data, ?string $ip): DocumentSignature
    {
        $pngBinary = $this->decodeBase64Png($data['signature_data']);
        $hash      = hash('sha256', $pngBinary);
        $path      = "document-signatures/document-{$document->id}-{$signerRole}-{$hash}.png";

        Storage::disk('public')->put($path, $pngBinary);

        $signature = DocumentSignature::create([
            'clinic_id'        => $document->clinic_id,
            'document_id'      => $document->id,
            'signer_role'      => $signerRole,
            'signer_name'      => $data['signer_name'] ?? null,
            'signer_cpf'       => $data['signer_cpf'] ?? null,
            'signer_email'     => $data['signer_email'] ?? null,
            'professional_id'  => $data['professional_id'] ?? null,
            'professional_cro' => $data['professional_cro'] ?? null,
            'signature_path'   => $path,
            'signature_hash'   => $hash,
            'ip_address'       => $ip,
            'user_agent'       => $data['user_agent'] ?? null,
            'timezone'         => $data['timezone'] ?? null,
            'browser_info'     => $data['browser_info'] ?? null,
            'geolocation'      => $data['geolocation'] ?? null,
            'signed_at'        => now(),
        ]);

        $this->statusService->advanceAfterSignature($document, $signerRole);

        return $signature;
    }

    public function driver(): string
    {
        return 'local';
    }

    private function decodeBase64Png(string $dataUri): string
    {
        $base64 = $dataUri;
        if (str_contains($dataUri, ',')) {
            $base64 = explode(',', $dataUri, 2)[1];
        }

        return base64_decode($base64, true) ?: throw new \RuntimeException('Invalid signature PNG data.');
    }
}
