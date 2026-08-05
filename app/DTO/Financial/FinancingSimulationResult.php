<?php

namespace App\DTO\Financial;

readonly class FinancingSimulationResult
{
    public function __construct(
        public string $provider,
        public string $providerName,
        public int $installments,
        public float $installmentValue,
        public float $totalAmount,
        public float $interestRate,
        public float $cet,
        public float $fees,
        public int $termMonths,
        public ?string $externalId = null,
        public array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'provider'           => $this->provider,
            'provider_name'      => $this->providerName,
            'installments'       => $this->installments,
            'installment_value'  => $this->installmentValue,
            'total_amount'       => $this->totalAmount,
            'interest_rate'      => $this->interestRate,
            'cet'                => $this->cet,
            'fees'               => $this->fees,
            'term_months'        => $this->termMonths,
            'external_id'        => $this->externalId,
        ];
    }
}