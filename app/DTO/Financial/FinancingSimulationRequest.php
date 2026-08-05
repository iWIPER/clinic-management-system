<?php

namespace App\DTO\Financial;

readonly class FinancingSimulationRequest
{
    public function __construct(
        public string $cpf,
        public float $amount,
        public int $installments,
        public ?int $budgetId = null,
        public ?int $patientId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'cpf'          => $this->cpf,
            'valor'        => $this->amount,
            'parcelas'     => $this->installments,
            'budget_id'    => $this->budgetId,
            'patient_id'   => $this->patientId,
        ];
    }
}