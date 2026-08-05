<?php

namespace App\Console\Commands;

use App\Services\PatientStatusService;
use Illuminate\Console\Command;

class UpdatePatientAutoStatus extends Command
{
    protected $signature = 'patients:update-auto-status';

    protected $description = 'Recalcula o status automático de todos os pacientes com base no último procedimento concluído';

    public function handle(PatientStatusService $statusService): int
    {
        $this->info('Recalculando status dos pacientes...');
        $updated = $statusService->recalculateAll();
        $this->info("Concluído. {$updated} paciente(s) atualizado(s).");

        return Command::SUCCESS;
    }
}
