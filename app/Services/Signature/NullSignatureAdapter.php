<?php

namespace App\Services\Signature;

use App\Contracts\Signature\SignatureAdapterInterface;
use App\Models\AnamnesisInstance;

/**
 * Adapter padrão (sem integração externa).
 * Preserva o comportamento atual — assinatura marcada manualmente ao completar.
 */
class NullSignatureAdapter implements SignatureAdapterInterface
{
    public function requestSignature(AnamnesisInstance $instance, array $signers): array
    {
        return [
            'provider_id' => 'local-' . $instance->id,
            'sign_url' => null,
            'driver' => $this->driver(),
        ];
    }

    public function checkStatus(string $providerId): array
    {
        return ['status' => 'pending', 'signed_at' => null];
    }

    public function cancel(string $providerId): bool
    {
        return true;
    }

    public function downloadSigned(string $providerId): string
    {
        throw new \RuntimeException('NullSignatureAdapter does not support PDF download.');
    }

    public function driver(): string
    {
        return 'null';
    }
}
