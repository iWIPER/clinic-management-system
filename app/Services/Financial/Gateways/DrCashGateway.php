<?php

namespace App\Services\Financial\Gateways;

class DrCashGateway extends AbstractFinancialGateway
{
    protected function providerSlug(): string
    {
        return 'dr_cash';
    }
}