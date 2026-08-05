<?php

namespace App\Services\Financial;

use App\Models\ClinicFinancialConnection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class FinancialConnectionService
{
    public function __construct(
        private FinancialGatewayManager $gatewayManager,
    ) {}

    public function upsert(int $clinicId, string $provider, array $data): ClinicFinancialConnection
    {
        $connection = ClinicFinancialConnection::firstOrNew([
            'clinic_id' => $clinicId,
            'provider'  => $provider,
        ]);

        $connection->environment = $data['environment'] ?? 'sandbox';

        if (!empty($data['client_id'])) {
            $connection->client_id = Crypt::encryptString($data['client_id']);
        }

        if (!empty($data['client_secret'])) {
            $connection->client_secret = Crypt::encryptString($data['client_secret']);
        }

        if (!$connection->webhook_secret) {
            $connection->webhook_secret = Crypt::encryptString(Str::random(48));
        }

        $connection->webhook_url = route('financial.webhooks.receive', [
            'provider'     => $provider,
            'connectionId' => $connection->id ?? 'new',
        ]);

        $connection->metadata = array_merge($connection->metadata ?? [], [
            'use_mock' => ($data['environment'] ?? 'sandbox') === 'sandbox',
        ]);

        $connection->status = 'inactive';
        $connection->save();

        // Atualiza URL com ID real
        $connection->update([
            'webhook_url' => route('financial.webhooks.receive', [
                'provider'     => $provider,
                'connectionId' => $connection->id,
            ]),
        ]);

        return $connection->fresh();
    }

    public function test(string $provider, int $clinicId): array
    {
        $connection = ClinicFinancialConnection::where('clinic_id', $clinicId)
            ->where('provider', $provider)
            ->firstOrFail();

        $gateway = $this->gatewayManager->forConnection($connection);
        $report  = $gateway->testIntegration($connection);

        return $report->toArray();
    }

    public function listForClinic(int $clinicId): array
    {
        $connections = ClinicFinancialConnection::where('clinic_id', $clinicId)->get()->keyBy('provider');

        return collect($this->gatewayManager->catalog())
            ->map(function ($inst) use ($connections) {
                $conn = $connections->get($inst['slug']);

                return [
                    ...$inst,
                    'connected'      => $conn?->isActive() ?? false,
                    'status'         => $conn?->status ?? 'inactive',
                    'environment'    => $conn?->environment,
                    'last_tested_at' => $conn?->last_tested_at?->toIso8601String(),
                    'last_sync_at'   => $conn?->last_sync_at?->toIso8601String(),
                    'webhook_url'    => $conn?->webhook_url,
                    'has_credentials'=> (bool) $conn?->client_id,
                ];
            })
            ->all();
    }
}