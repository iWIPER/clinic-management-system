<?php

namespace App\DTO\Financial;

readonly class FinancingProposalRequest
{
    public function __construct(
        public string $provider,
        public int $budgetId,
        public int $patientId,
        public string $name,
        public string $cpf,
        public string $phone,
        public string $email,
        public float $amount,
        public int $installments,
        public ?string $simulationExternalId = null,
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'provider'               => $this->provider,
            'budget_id'              => $this->budgetId,
            'patient_id'             => $this->patientId,
            'nome'                   => $this->name,
            'cpf'                    => $this->cpf,
            'telefone'               => $this->phone,
            'email'                  => $this->email,
            'valor'                  => $this->amount,
            'parcelas'               => $this->installments,
            'simulation_external_id' => $this->simulationExternalId,
            'metadata'               => $this->metadata,
        ];
    }
}