<?php

namespace App\Services\Financial\Gateways;

class DentalCredGateway extends AbstractFinancialGateway
{
    protected function providerSlug(): string
    {
        return 'dental_cred';
    }
}