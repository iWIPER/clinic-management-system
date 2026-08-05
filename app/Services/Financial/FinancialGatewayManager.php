<?php

namespace App\Services\Financial;

use App\Contracts\Financial\FinancialGatewayInterface;
use App\Exceptions\Financial\FinancialGatewayException;
use App\Models\ClinicFinancialConnection;

class FinancialGatewayManager
{
    /** @var array<string, FinancialGatewayInterface> */
    private array $resolved = [];

    public function resolve(string $provider): FinancialGatewayInterface
    {
        if (isset($this->resolved[$provider])) {
            return $this->resolved[$provider];
        }

        $class = config("financial.gateways.{$provider}");

        if (!$class || !class_exists($class)) {
            throw new FinancialGatewayException(
                "Gateway não registrado: {$provider}",
                $provider,
                'Instituição financeira não suportada.'
            );
        }

        $gateway = app($class);

        if (!$gateway instanceof FinancialGatewayInterface) {
            throw new FinancialGatewayException(
                "Gateway inválido: {$provider}",
                $provider,
            );
        }

        return $this->resolved[$provider] = $gateway;
    }

    public function forConnection(ClinicFinancialConnection $connection): FinancialGatewayInterface
    {
        return $this->resolve($connection->provider);
    }

    /** @return array<string, FinancialGatewayInterface> */
    public function all(): array
    {
        $gateways = [];

        foreach (array_keys(config('financial.gateways', [])) as $provider) {
            $gateways[$provider] = $this->resolve($provider);
        }

        return $gateways;
    }

    public function catalog(): array
    {
        return collect(config('financial.institutions', []))
            ->map(fn ($inst, $slug) => [
                'slug'        => $slug,
                'name'        => $inst['name'],
                'product'     => $inst['product'],
                'description' => $inst['description'],
            ])
            ->values()
            ->all();
    }
}