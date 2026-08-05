<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ProcessReferralEligibility extends Command
{
    protected $signature = 'referrals:process-eligibility';

    protected $description = 'Processa conversões elegíveis para liberação de bônus';

    public function handle(SubscriptionService $service): int
    {
        $count = $service->processEligibleConversions();
        $this->info("{$count} conversão(ões) processada(s).");

        return self::SUCCESS;
    }
}