<?php

namespace App\Services\Financial;

use App\Enums\Financial\FinancingProposalStatus;
use App\Enums\Financial\FinancingWebhookEventType;
use App\Models\ClinicFinancialConnection;
use App\Models\FinancingActivityLog;
use App\Models\FinancingProposal;
use App\Models\FinancingWebhookEvent;
use Illuminate\Support\Facades\Log;

class FinancingWebhookProcessor
{
    public function __construct(
        private FinancialGatewayManager $gatewayManager,
        private FinancingSettlementService $settlementService,
    ) {}

    public function receive(
        ClinicFinancialConnection $connection,
        array $payload,
        ?string $signature = null,
    ): FinancingWebhookEvent {
        $this->verifySignature($connection, $payload, $signature);

        $gateway = $this->gatewayManager->forConnection($connection);
        $parsed  = $gateway->parseWebhookPayload($payload);

        $event = FinancingWebhookEvent::create([
            'clinic_id'     => $connection->clinic_id,
            'connection_id' => $connection->id,
            'provider'      => $connection->provider,
            'event_type'    => $parsed['event_type']->value,
            'external_id'   => $parsed['external_id'] ?: null,
            'payload'       => $payload,
            'status'        => 'received',
        ]);

        try {
            $this->applyEvent($connection, $event, $parsed);
            $event->update(['status' => 'processed', 'processed_at' => now()]);
            $connection->update(['last_sync_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('[FinancingWebhook] Falha ao processar', [
                'event_id' => $event->id,
                'error'    => $e->getMessage(),
            ]);
            $event->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }

        return $event;
    }

    private function applyEvent(ClinicFinancialConnection $connection, FinancingWebhookEvent $event, array $parsed): void
    {
        if (empty($parsed['external_id'])) {
            return;
        }

        $proposal = FinancingProposal::where('clinic_id', $connection->clinic_id)
            ->where('provider', $connection->provider)
            ->where('external_id', $parsed['external_id'])
            ->first();

        if (!$proposal) {
            return;
        }

        $event->update(['proposal_id' => $proposal->id]);

        $newStatus = $this->mapStatus($parsed['event_type']);
        $updates   = ['status' => $newStatus->value];

        if ($parsed['signature_url']) {
            $updates['signature_url'] = $parsed['signature_url'];
        }

        if ($parsed['event_type'] === FinancingWebhookEventType::Signed) {
            $updates['signed_at'] = now();
        }

        $proposal->update($updates);

        if (in_array($parsed['event_type'], [FinancingWebhookEventType::Paid, FinancingWebhookEventType::Settled], true)) {
            $this->settlementService->settle($proposal, $parsed);
        }

        FinancingActivityLog::create([
            'clinic_id'   => $proposal->clinic_id,
            'budget_id'   => $proposal->budget_id,
            'patient_id'  => $proposal->patient_id,
            'proposal_id' => $proposal->id,
            'event_type'  => 'financing_webhook_' . $parsed['event_type']->value,
            'description' => "Webhook: {$parsed['event_type']->value}",
            'metadata'    => $parsed['raw'] ?? [],
        ]);
    }

    private function mapStatus(FinancingWebhookEventType $event): FinancingProposalStatus
    {
        return match ($event) {
            FinancingWebhookEventType::ProposalCreated   => FinancingProposalStatus::Created,
            FinancingWebhookEventType::UnderReview       => FinancingProposalStatus::UnderReview,
            FinancingWebhookEventType::Approved          => FinancingProposalStatus::Approved,
            FinancingWebhookEventType::Rejected          => FinancingProposalStatus::Rejected,
            FinancingWebhookEventType::AwaitingSignature => FinancingProposalStatus::AwaitingSignature,
            FinancingWebhookEventType::Signed            => FinancingProposalStatus::Signed,
            FinancingWebhookEventType::Paid              => FinancingProposalStatus::Paid,
            FinancingWebhookEventType::Cancelled         => FinancingProposalStatus::Cancelled,
            FinancingWebhookEventType::Settled           => FinancingProposalStatus::Settled,
            default                                      => FinancingProposalStatus::UnderReview,
        };
    }

    private function verifySignature(ClinicFinancialConnection $connection, array $payload, ?string $signature): void
    {
        if ($connection->isSandbox()) {
            return;
        }

        if (!$signature || !$connection->webhook_secret) {
            return;
        }

        $expected = hash_hmac(
            'sha256',
            json_encode($payload),
            \Illuminate\Support\Facades\Crypt::decryptString($connection->webhook_secret)
        );

        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('Assinatura de webhook inválida.');
        }
    }
}