<?php

namespace App\Services\Financial;

use App\Exceptions\Financial\FinancialGatewayUnavailableException;
use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    private function failuresKey(string $provider, int $clinicId): string
    {
        return "financial_cb_failures:{$clinicId}:{$provider}";
    }

    private function openKey(string $provider, int $clinicId): string
    {
        return "financial_cb_open:{$clinicId}:{$provider}";
    }

    public function isOpen(string $provider, int $clinicId): bool
    {
        return Cache::has($this->openKey($provider, $clinicId));
    }

    public function guard(string $provider, int $clinicId): void
    {
        if ($this->isOpen($provider, $clinicId)) {
            throw new FinancialGatewayUnavailableException($provider, 'circuit breaker aberto');
        }
    }

    public function recordSuccess(string $provider, int $clinicId): void
    {
        Cache::forget($this->failuresKey($provider, $clinicId));
        Cache::forget($this->openKey($provider, $clinicId));
    }

    public function recordFailure(string $provider, int $clinicId): void
    {
        $key      = $this->failuresKey($provider, $clinicId);
        $failures = (int) Cache::get($key, 0) + 1;
        $threshold = config('financial.circuit_breaker.failure_threshold', 5);
        $openSeconds = config('financial.circuit_breaker.open_seconds', 120);

        Cache::put($key, $failures, now()->addMinutes(30));

        if ($failures >= $threshold) {
            Cache::put($this->openKey($provider, $clinicId), true, $openSeconds);
        }
    }
}