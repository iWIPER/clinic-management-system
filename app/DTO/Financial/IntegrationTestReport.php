<?php

namespace App\DTO\Financial;

readonly class IntegrationTestReport
{
    public function __construct(
        public string $provider,
        public string $providerName,
        public string $environment,
        public bool $success,
        public int $healthScore,
        public array $checks,
        public array $recommendations,
        public ?int $responseTimeMs = null,
        public ?string $lastSyncAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'provider'         => $this->provider,
            'provider_name'    => $this->providerName,
            'environment'      => $this->environment,
            'success'          => $this->success,
            'health_score'     => $this->healthScore,
            'checks'           => $this->checks,
            'recommendations'  => $this->recommendations,
            'response_time_ms' => $this->responseTimeMs,
            'last_sync_at'     => $this->lastSyncAt,
        ];
    }
}