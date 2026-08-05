<?php

namespace App\Jobs;

use App\Models\ClinicFinancialConnection;
use App\Models\FinancingProposal;
use App\Services\Financial\FinancingProposalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SubmitFinancingProposalJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $proposalId,
        public int $connectionId,
    ) {}

    public function handle(FinancingProposalService $proposalService): void
    {
        $proposal   = FinancingProposal::findOrFail($this->proposalId);
        $connection = ClinicFinancialConnection::findOrFail($this->connectionId);

        try {
            $proposalService->processSubmission($proposal, $connection);
        } catch (\Throwable $e) {
            Log::error('[SubmitFinancingProposalJob] Falha', [
                'proposal_id' => $this->proposalId,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}