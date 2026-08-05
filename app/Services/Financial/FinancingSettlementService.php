<?php

namespace App\Services\Financial;

use App\Models\Budget;
use App\Models\FinancingActivityLog;
use App\Models\FinancingProposal;
use App\Models\Transaction;

class FinancingSettlementService
{
    public function settle(FinancingProposal $proposal, array $webhookData): void
    {
        if ($proposal->transaction_id) {
            return;
        }

        $netAmount  = $webhookData['net_amount'] ?? (float) $proposal->amount;
        $feesAmount = $webhookData['fees_amount'] ?? 0;
        $settledAt  = $webhookData['settled_at'] ?? now()->toDateString();

        $transaction = Transaction::create([
            'clinic_id'    => $proposal->clinic_id,
            'patient_id'   => $proposal->patient_id,
            'tipo'         => 'receita',
            'valor'        => $netAmount,
            'categoria'    => 'Financiamento',
            'descricao'    => "Liquidação {$proposal->provider} — orçamento #{$proposal->budget_id}",
            'origem_type'  => FinancingProposal::class,
            'origem_id'    => $proposal->id,
            'caixa'        => 'principal',
            'status'       => 'pago',
            'pago_em'      => now(),
        ]);

        $proposal->update([
            'net_amount'               => $netAmount,
            'fees_amount'              => $feesAmount,
            'settled_at'               => $settledAt,
            'expected_settlement_date' => $settledAt,
            'transaction_id'           => $transaction->id,
            'status'                   => 'liquidada',
        ]);

        Budget::where('id', $proposal->budget_id)->update(['status' => 'convertido']);

        FinancingActivityLog::create([
            'clinic_id'   => $proposal->clinic_id,
            'budget_id'   => $proposal->budget_id,
            'patient_id'  => $proposal->patient_id,
            'proposal_id' => $proposal->id,
            'event_type'  => 'financing_settled',
            'description' => "Pagamento liquidado — R$ {$netAmount} (taxas: R$ {$feesAmount})",
            'metadata'    => [
                'transaction_id' => $transaction->id,
                'provider'       => $proposal->provider,
                'net_amount'     => $netAmount,
                'fees_amount'    => $feesAmount,
            ],
        ]);
    }
}