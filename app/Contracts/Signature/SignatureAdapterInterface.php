<?php

namespace App\Contracts\Signature;

use App\Models\AnamnesisInstance;

interface SignatureAdapterInterface
{
    /**
     * Cria uma solicitação de assinatura para o documento da instância.
     * Retorna um array com provider_id, sign_url e quaisquer metadados específicos.
     */
    public function requestSignature(AnamnesisInstance $instance, array $signers): array;

    /**
     * Verifica o status atual de uma solicitação de assinatura.
     * Retorna: ['status' => 'pending|signed|expired|cancelled', 'signed_at' => ISO8601|null]
     */
    public function checkStatus(string $providerId): array;

    /**
     * Cancela uma solicitação de assinatura em aberto.
     */
    public function cancel(string $providerId): bool;

    /**
     * Baixa o PDF assinado e retorna o conteúdo binário.
     */
    public function downloadSigned(string $providerId): string;

    /**
     * Retorna o identificador único do adapter (ex: 'null', 'zapsign', 'autentique').
     */
    public function driver(): string;
}
