<?php

namespace App\Exceptions\Financial;

class FinancialGatewayUnavailableException extends FinancialGatewayException
{
    public function __construct(string $provider, ?string $reason = null)
    {
        parent::__construct(
            message: "Gateway {$provider} indisponível" . ($reason ? ": {$reason}" : ''),
            provider: $provider,
            userMessage: 'Esta instituição financeira está temporariamente indisponível. O restante do sistema continua funcionando normalmente.',
        );
    }
}