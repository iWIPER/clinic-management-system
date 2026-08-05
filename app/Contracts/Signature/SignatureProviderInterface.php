<?php

namespace App\Contracts\Signature;

use App\Models\AnamnesisInstance;
use App\Models\AnamnesisSignature;

interface SignatureProviderInterface
{
    /**
     * Processa e persiste uma assinatura para a instância.
     * $data deve conter: signature_data (base64 PNG), patient_name,
     * patient_cpf (nullable), patient_email (nullable), timezone,
     * browser_info (array), geolocation (nullable array).
     */
    public function sign(AnamnesisInstance $instance, array $data, ?string $ip): AnamnesisSignature;

    /**
     * Retorna o identificador do provedor.
     */
    public function driver(): string;

    /**
     * Indica se o provedor suporta identidade por OAuth externo.
     */
    public function supportsOAuth(): bool;
}
