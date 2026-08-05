<?php

namespace App\Contracts\Financial;

use App\DTO\Financial\FinancingProposalRequest;
use App\DTO\Financial\FinancingProposalResult;
use App\DTO\Financial\FinancingSimulationRequest;
use App\DTO\Financial\FinancingSimulationResult;
use App\DTO\Financial\IntegrationTestReport;
use App\Models\ClinicFinancialConnection;

interface FinancialGatewayInterface
{
    public function provider(): string;

    public function displayName(): string;

    /**
     * Testa conectividade, token, permissões e latência.
     */
    public function testIntegration(ClinicFinancialConnection $connection): IntegrationTestReport;

    /**
     * Simulação sob demanda — nunca automática.
     *
     * @return FinancingSimulationResult[]
     */
    public function simulate(ClinicFinancialConnection $connection, FinancingSimulationRequest $request): array;

    /**
     * Submete proposta após aprovação do paciente na simulação.
     */
    public function submitProposal(ClinicFinancialConnection $connection, FinancingProposalRequest $request): FinancingProposalResult;

    /**
     * Processa payload de webhook da instituição.
     */
    public function parseWebhookPayload(array $payload): array;
}