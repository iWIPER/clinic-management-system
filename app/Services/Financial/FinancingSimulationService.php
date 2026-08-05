<?php

namespace App\Services\Financial;

use App\DTO\Financial\FinancingSimulationRequest;
use App\Exceptions\Financial\FinancialGatewayException;
use App\Exceptions\Financial\FinancialGatewayUnavailableException;
use App\Models\ClinicFinancialConnection;
use App\Models\FinancingActivityLog;
use App\Models\FinancingSimulation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class FinancingSimulationService
{
    public function __construct(
        private FinancialGatewayManager $gatewayManager,
    ) {}

    /**
     * Simulação sob demanda em todas as instituições ativas da clínica.
     */
    public function simulateForBudget(
        int $clinicId,
        FinancingSimulationRequest $request,
        User $user,
    ): array {
        $connections = ClinicFinancialConnection::where('clinic_id', $clinicId)
            ->where('status', 'active')
            ->get();

        if ($connections->isEmpty()) {
            throw new FinancialGatewayException(
                'Nenhuma instituição financeira ativa',
                '',
                'Conecte e teste ao menos uma instituição no Marketplace Financeiro antes de simular.'
            );
        }

        $results  = [];
        $failures = [];

        foreach ($connections as $connection) {
            try {
                $gateway   = $this->gatewayManager->forConnection($connection);
                $simulations = $gateway->simulate($connection, $request);

                foreach ($simulations as $sim) {
                    FinancingSimulation::create([
                        'clinic_id'         => $clinicId,
                        'budget_id'         => $request->budgetId,
                        'patient_id'        => $request->patientId,
                        'requested_by'      => $user->id,
                        'provider'          => $sim->provider,
                        'amount'            => $request->amount,
                        'installments'      => $sim->installments,
                        'cpf_hash'          => $this->hashCpf($request->cpf),
                        'external_id'       => $sim->externalId,
                        'installment_value' => $sim->installmentValue,
                        'total_amount'      => $sim->totalAmount,
                        'interest_rate'     => $sim->interestRate,
                        'cet'               => $sim->cet,
                        'fees'              => $sim->fees,
                        'raw_response'      => $sim->raw,
                    ]);

                    $results[] = $sim->toArray();
                }
            } catch (FinancialGatewayUnavailableException $e) {
                $failures[] = [
                    'provider' => $connection->provider,
                    'message'  => $e->forUser(),
                    'offline'  => true,
                ];
            } catch (FinancialGatewayException $e) {
                $failures[] = [
                    'provider' => $connection->provider,
                    'message'  => $e->forUser(),
                    'offline'  => false,
                ];
            }
        }

        FinancingActivityLog::create([
            'clinic_id'  => $clinicId,
            'budget_id'  => $request->budgetId,
            'patient_id' => $request->patientId,
            'user_id'    => $user->id,
            'event_type' => 'financing_simulation_requested',
            'description'=> count($results) > 0
                ? 'Simulação de financiamento realizada com sucesso.'
                : 'Simulação solicitada, mas nenhuma instituição retornou propostas.',
            'metadata'   => [
                'results_count' => count($results),
                'failures'      => $failures,
            ],
        ]);

        return [
            'simulations' => $results,
            'failures'    => $failures,
            'compared'    => $this->rankSimulations($results),
        ];
    }

    private function hashCpf(string $cpf): string
    {
        $digits = preg_replace('/\D/', '', $cpf);

        return hash('sha256', $digits . config('app.key'));
    }

    private function rankSimulations(array $results): array
    {
        return collect($results)
            ->sortBy('cet')
            ->values()
            ->map(fn ($r, $i) => array_merge($r, ['rank' => $i + 1]))
            ->all();
    }
}