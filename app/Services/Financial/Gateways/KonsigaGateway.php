<?php

namespace App\Services\Financial\Gateways;

class KonsigaGateway extends AbstractFinancialGateway
{
    protected function providerSlug(): string
    {
        return 'konsiga';
    }
}