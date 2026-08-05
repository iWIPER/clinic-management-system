<?php

namespace App\Exceptions\Financial;

use Exception;

class FinancialGatewayException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $provider = '',
        public readonly ?string $userMessage = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function forUser(): string
    {
        return $this->userMessage ?? 'A integração financeira não pôde ser concluída neste momento.';
    }
}