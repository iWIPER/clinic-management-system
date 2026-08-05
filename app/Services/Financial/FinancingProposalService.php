<?php

namespace App\Services\Financial;

use App\DTO\Financial\FinancingProposalRequest;
use App\Enums\Financial\FinancingProposalStatus;
use App\Jobs\SubmitFinancingProposalJob;
use App\Models\Budget;
use App\Models\ClinicFinancialConnection;
use App\Models\FinancingActivityLog;
use App\Models\FinancingProposal;
use App\Models\FinancingSimulation;
use App\Models\User;

class FinancingProposalService
{
    public function __construct(
        private FinancialGatewayManager $gatewayManager,
    ) {}

    public function submit(FinancingProposalRequest $request, User $user): FinancingProposal
    {
        $budget = Budget::findOrFail($request->budgetId);

        $connection = ClinicFinancialConnection::where('clinic_id', $budget->clinic_id)
            ->where('provider', $request->provider)
            ->where('status', 'active')
            ->firstOrFail();

        $simulation = null;
        if ($request->simulationExternalId) {
            $simulation = FinancingSimulation::where('external_id', $request->simulationExternalId)
                ->where('clinic_id', $budget->clinic_id)
                ->first();
        }

        $proposal = FinancingProposal::create([
            'clinic_id'     => $budget->clinic_id,
            'budget_id'     => $budget->id,
            'patient_id'    => $request->patientId,
            'simulation_id' => $simulation?->id,
            'submitted_by'  => $user->id,
            'provider'      => $request->provider,
            'status'        => FinancingProposalStatus::Draft->value,
            'amount'        => $request->amount,
            'installments'  => $request->installments,
            'metadata'      => ['request' => $request->toArray()],
        ]);

        SubmitFinancingProposalJob::dispatch($proposal->id, $connection->id);

        FinancingActivityLog::create([
            'clinic_id'   => $budget->clinic_id,
            'budget_id'   => $budget->id,
            'patient_id'  => $request->patientId,
            'proposal_id' => $proposal->id,
            'user_id'     => $user->id,
            'event_type'  => 'financing_proposal_submitted',
            'description' => "Proposta enviada para {$connection->provider}.",
        ]);

        return $proposal;
    }

    public function processSubmission(FinancingProposal $proposal, ClinicFinancialConnection $connection): void
    {
        $gateway = $this->gatewayManager->forConnection($connection);
        $meta    = $proposal->metadata['request'] ?? [];

        $result = $gateway->submitProposal($connection, new FinancingProposalRequest(
            provider: $proposal->provider,
            budgetId: $proposal->budget_id,
            patientId: $proposal->patient_id,
            name: $meta['nome'] ?? '',
            cpf: $meta['cpf'] ?? '',
            phone: $meta['telefone'] ?? '',
            email: $meta['email'] ?? '',
            amount: (float) $proposal->amount,
            installments: (int) $proposal->installments,
            simulationExternalId: $meta['simulation_external_id'] ?? null,
            metadata: $meta,
        ));

        $proposal->update([
            'external_id'   => $result->externalId,
            'status'        => $result->status->value,
            'signature_url' => $result->signatureUrl,
            'metadata'      => array_merge($proposal->metadata ?? [], ['response' => $result->raw]),
        ]);

        $connection->update(['last_sync_at' => now()]);

        FinancingActivityLog::create([
            'clinic_id'   => $proposal->clinic_id,
            'budget_id'   => $proposal->budget_id,
            'patient_id'  => $proposal->patient_id,
            'proposal_id' => $proposal->id,
            'event_type'  => 'financing_proposal_created',
            'description' => $result->signatureUrl
                ? 'Proposta criada. Link de assinatura disponível.'
                : 'Proposta enviada. Aguardando retorno da instituição.',
            'metadata'    => ['signature_url' => $result->signatureUrl],
        ]);
    }
}