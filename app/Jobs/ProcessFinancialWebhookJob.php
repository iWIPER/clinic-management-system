<?php

namespace App\Jobs;

use App\Models\ClinicFinancialConnection;
use App\Services\Financial\FinancingWebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessFinancialWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $connectionId,
        public array $payload,
        public ?string $signature = null,
    ) {}

    public function handle(FinancingWebhookProcessor $processor): void
    {
        $connection = ClinicFinancialConnection::findOrFail($this->connectionId);
        $processor->receive($connection, $this->payload, $this->signature);
    }
}