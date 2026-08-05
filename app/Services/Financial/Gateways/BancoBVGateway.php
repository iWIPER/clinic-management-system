<?php

namespace App\Services\Financial\Gateways;

class BancoBVGateway extends AbstractFinancialGateway
{
    protected function providerSlug(): string
    {
        return 'banco_bv';
    }
}