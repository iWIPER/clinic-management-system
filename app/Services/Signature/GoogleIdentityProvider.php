<?php

namespace App\Services\Signature;

use App\Contracts\Signature\SignatureProviderInterface;
use App\Models\AnamnesisInstance;
use App\Models\AnamnesisSignature;

/**
 * Stub — Google Identity Provider para assinatura eletrônica.
 * Quando implementado, usará Google OAuth 2.0 para validar identidade do paciente.
 * Configure: GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET no .env
 */
class GoogleIdentityProvider implements SignatureProviderInterface
{
    public function sign(AnamnesisInstance $instance, array $data, ?string $ip): AnamnesisSignature
    {
        throw new \RuntimeException('GoogleIdentityProvider not yet implemented. Use LocalSignatureProvider.');
    }

    public function driver(): string
    {
        return 'google';
    }

    public function supportsOAuth(): bool
    {
        return true;
    }
}
