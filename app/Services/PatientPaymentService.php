<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientPayment;
use App\Models\PatientTreatment;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PatientPaymentService
{
    /**
     * Os 4 cards da aba Pagamentos — sempre o agregado completo do
     * paciente (não afetado pelo filtro de status da lista abaixo, para os
     * números nunca parecerem mudar "sozinhos" ao filtrar).
     *
     * @return array{received: float, outstanding: float, overdue: float, total_charged: float}
     */
    public function summary(Patient $patient): array
    {
        $today = Carbon::today();

        $received = 0.0;
        $outstanding = 0.0;
        $overdue = 0.0;
        $totalCharged = 0.0;

        PatientPayment::where('patient_id', $patient->id)
            ->where('status', '!=', PatientPayment::STATUS_CANCELADO)
            ->get(['amount', 'amount_paid', 'status', 'due_date'])
            ->each(function (PatientPayment $payment) use (&$received, &$outstanding, &$overdue, &$totalCharged, $today) {
                $amount    = (float) $payment->amount;
                $remaining = $payment->remaining();

                $received     += (float) $payment->amount_paid;
                $totalCharged += $amount;

                if ($remaining <= 0 || ! in_array($payment->status, [PatientPayment::STATUS_PENDENTE, PatientPayment::STATUS_PARCIAL], true)) {
                    return;
                }

                if ($payment->due_date && $payment->due_date->lt($today)) {
                    $overdue += $remaining;
                } else {
                    $outstanding += $remaining;
                }
            });

        return [
            'received'      => round($received, 2),
            'outstanding'   => round($outstanding, 2),
            'overdue'       => round($overdue, 2),
            'total_charged' => round($totalCharged, 2),
        ];
    }

    /**
     * Substitui todas as PatientPayment (e Transaction ligadas) de um
     * tratamento por um novo plano de N parcelas. O chamador (controller) já
     * garantiu que nenhuma delas tem amount_paid > 0 — aqui só a mecânica.
     *
     * Divide value_charged em centavos inteiros pra nunca perder/inventar
     * centavo por arredondamento (ex.: 100,00 em 3x = 33,33 + 33,33 + 33,34,
     * nunca 33,33 x3 = 99,99) — a última parcela absorve o resto da divisão.
     */
    public function generatePlan(PatientTreatment $treatment, int $installments, Carbon $firstDueDate, int $intervalDays, ?int $createdById): void
    {
        DB::transaction(function () use ($treatment, $installments, $firstDueDate, $intervalDays, $createdById) {
            $existing = PatientPayment::where('patient_treatment_id', $treatment->id)->get();
            foreach ($existing as $payment) {
                if ($payment->transaction_id) {
                    $payment->transaction()->delete();
                }
            }
            PatientPayment::where('patient_treatment_id', $treatment->id)->delete();

            $totalCents = (int) round((float) $treatment->value_charged * 100);
            $baseCents = intdiv($totalCents, $installments);
            $remainderCents = $totalCents - ($baseCents * $installments);

            for ($i = 1; $i <= $installments; $i++) {
                $amountCents = $baseCents + ($i === $installments ? $remainderCents : 0);
                $amount = round($amountCents / 100, 2);

                $payment = PatientPayment::create([
                    'clinic_id'            => $treatment->clinic_id,
                    'patient_id'           => $treatment->patient_id,
                    'patient_treatment_id' => $treatment->id,
                    'installment_number'   => $i,
                    'installment_total'    => $installments,
                    'amount'               => $amount,
                    'status'               => PatientPayment::STATUS_PENDENTE,
                    'due_date'             => $firstDueDate->copy()->addDays($intervalDays * ($i - 1))->toDateString(),
                    'created_by_id'        => $createdById,
                ]);

                $transaction = Transaction::create([
                    'clinic_id'   => $treatment->clinic_id,
                    'patient_id'  => $treatment->patient_id,
                    'tipo'        => 'receita',
                    'valor'       => $amount,
                    'categoria'   => 'Tratamento',
                    'descricao'   => "{$treatment->procedure_name} — parcela {$i}/{$installments}",
                    'origem_type' => PatientPayment::class,
                    'origem_id'   => $payment->id,
                    'status'      => 'pendente',
                ]);

                $payment->update(['transaction_id' => $transaction->id]);
            }
        });
    }
}
