<?php

namespace App\Services\Signature;

use App\Contracts\Signature\SignatureAdapterInterface;
use App\Models\AnamnesisInstance;

/**
 * Fachada de assinatura eletrônica.
 * O adapter ativo é determinado por SIGNATURE_DRIVER no .env (padrão: null).
 */
class SignatureService
{
    public function __construct(private SignatureAdapterInterface $adapter) {}

    public function requestSignature(AnamnesisInstance $instance, array $signers): array
    {
        return $this->adapter->requestSignature($instance, $signers);
    }

    public function checkStatus(string $providerId): array
    {
        return $this->adapter->checkStatus($providerId);
    }

    public function cancel(string $providerId): bool
    {
        return $this->adapter->cancel($providerId);
    }

    public function downloadSigned(string $providerId): string
    {
        return $this->adapter->downloadSigned($providerId);
    }

    public function driver(): string
    {
        return $this->adapter->driver();
    }
}
