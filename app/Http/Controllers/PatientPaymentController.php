<?php

namespace App\Http\Controllers;

use App\Exports\PatientPaymentsExport;
use App\Models\Patient;
use App\Models\PatientPayment;
use App\Models\PatientTreatment;
use App\Services\PatientPaymentExportService;
use App\Services\PatientPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class PatientPaymentController extends Controller
{
    public function receive(Request $request, Patient $patient, PatientPayment $patientPayment)
    {
        if (in_array($patientPayment->status, [PatientPayment::STATUS_PAGO, PatientPayment::STATUS_CANCELADO], true)) {
            return back()->with('error', 'Esta parcela não pode mais receber pagamentos.');
        }

        $validated = $request->validate([
            'amount_received' => ['required', 'numeric', 'min:0.01', 'max:' . $patientPayment->remaining()],
            'payment_method'  => ['required', 'string', 'in:' . implode(',', array_keys(PatientPayment::METHODS))],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ], [
            'amount_received.max' => 'O valor recebido não pode ser maior que o saldo devedor desta parcela.',
        ]);

        $patientPayment->registerPayment($validated['amount_received'], $validated['payment_method'], $validated['notes'] ?? null);

        return back()->with('success', 'Pagamento registrado.');
    }

    public function update(Request $request, Patient $patient, PatientPayment $patientPayment)
    {
        if ((float) $patientPayment->amount_paid > 0) {
            return back()->with('error', 'Não é possível editar uma parcela que já recebeu algum pagamento.');
        }

        $validated = $request->validate([
            'due_date'       => ['required', 'date'],
            'discount'       => ['nullable', 'numeric', 'min:0'],
            'interest'       => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:' . implode(',', array_keys(PatientPayment::METHODS))],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $patientPayment->update([
            'due_date'       => $validated['due_date'],
            'discount'       => $validated['discount'] ?? 0,
            'interest'       => $validated['interest'] ?? 0,
            'payment_method' => $validated['payment_method'] ?? null,
            'notes'          => $validated['notes'] ?? null,
            'updated_by_id'  => $request->user()->id,
        ]);

        return back()->with('success', 'Parcela atualizada.');
    }

    public function cancel(Patient $patient, PatientPayment $patientPayment)
    {
        if ((float) $patientPayment->amount_paid > 0 || $patientPayment->status === PatientPayment::STATUS_CANCELADO) {
            return back()->with('error', 'Só é possível cancelar uma parcela que ainda não recebeu nenhum pagamento.');
        }

        $patientPayment->cancel();

        return back()->with('success', 'Parcela cancelada.');
    }

    public function destroy(Patient $patient, PatientPayment $patientPayment)
    {
        if ((float) $patientPayment->amount_paid > 0 || $patientPayment->status !== PatientPayment::STATUS_PENDENTE) {
            return back()->with('error', 'Só é possível excluir uma parcela pendente que ainda não recebeu nenhum pagamento.');
        }

        // Nada foi recebido para esta parcela — a Transaction vinculada (se
        // houver) não representa nenhum evento financeiro real, então é
        // removida junto (diferente de cancel(), que preserva a Transaction
        // com status "cancelado" como rastro auditável de uma parcela que
        // continua existindo, só que encerrada). Guarda explícita em vez de
        // confiar no null-handling implícito do query builder para um delete.
        if ($patientPayment->transaction_id) {
            $patientPayment->transaction()->delete();
        }
        $patientPayment->delete();

        return back()->with('success', 'Parcela excluída.');
    }

    /**
     * "Criar plano de pagamento" — substitui a(s) parcela(s) atuais do
     * tratamento por um novo plano de N parcelas. Só permitido enquanto
     * nenhuma delas recebeu qualquer valor (mesma trava usada em
     * update()/cancel()/destroy(), aqui aplicada a todas as parcelas do
     * tratamento de uma vez).
     */
    public function createPlan(Request $request, Patient $patient, PatientTreatment $patientTreatment, PatientPaymentService $paymentService)
    {
        if (PatientPayment::where('patient_treatment_id', $patientTreatment->id)->where('amount_paid', '>', 0)->exists()) {
            return back()->with('error', 'Não é possível replanejar: pelo menos uma parcela deste tratamento já recebeu algum pagamento.');
        }

        $validated = $request->validate([
            'installments'    => ['required', 'integer', 'min:2', 'max:' . PatientPayment::MAX_INSTALLMENTS],
            'first_due_date'  => ['required', 'date'],
            'interval_days'   => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $paymentService->generatePlan(
            $patientTreatment,
            $validated['installments'],
            Carbon::parse($validated['first_due_date']),
            $validated['interval_days'],
            $request->user()->id,
        );

        return back()->with('success', "Plano de {$validated['installments']}x criado para {$patientTreatment->procedure_name}.");
    }

    /**
     * Comprovante simples (HTML puro, sem PDF/assinatura/NF-e — impressão ou
     * "salvar como PDF" fica a cargo do navegador). Só existe algo a
     * comprovar depois do primeiro recebimento.
     */
    public function receipt(Patient $patient, PatientPayment $patientPayment)
    {
        if (! in_array($patientPayment->status, [PatientPayment::STATUS_PARCIAL, PatientPayment::STATUS_PAGO], true)) {
            abort(404, 'Nenhum recebimento registrado para esta parcela ainda.');
        }

        $patientPayment->load('treatment:id,procedure_name,budget_code');

        return view('payments.receipt', [
            'payment' => $patientPayment,
            'patient' => $patient,
            'clinic'  => $patient->clinic,
        ]);
    }

    /**
     * Exporta exatamente os pagamentos que a aba exibiria com o mesmo filtro
     * de status — mesma lógica de PatientController::export() (patients),
     * inclusive o caso especial "atrasado" (não é um status persistido).
     */
    public function export(Request $request, Patient $patient, PatientPaymentExportService $exportService)
    {
        $format = $request->input('format', 'csv');
        abort_unless(in_array($format, ['csv', 'excel'], true), 422, 'Formato de exportação inválido.');

        $query = PatientPayment::where('patient_id', $patient->id)
            ->with(['treatment:id,procedure_name,budget_code']);

        $status = $request->input('payments_status');
        if ($status === 'atrasado') {
            $query->whereIn('status', [PatientPayment::STATUS_PENDENTE, PatientPayment::STATUS_PARCIAL])
                ->where('due_date', '<', now()->toDateString());
        } elseif (in_array($status, array_keys(PatientPayment::STATUSES), true)) {
            $query->where('status', $status);
        }

        [$periodFrom, $periodTo] = match ($request->input('payments_period')) {
            'este_mes'        => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'mes_passado'     => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'ultimos_90_dias' => [now()->subDays(90)->toDateString(), now()->toDateString()],
            default           => [null, null],
        };
        if ($periodFrom && $periodTo) {
            $query->whereBetween('due_date', [$periodFrom, $periodTo]);
        }

        $payments = $query->orderBy('due_date')->orderBy('id')->get();

        $filename = 'pagamentos-' . $patient->id . '-' . now()->format('Y-m-d');

        // Auditado antes do streaming começar (não depois: se o download for
        // interrompido no meio, ainda queremos o registro de que foi pedido)
        // — mesmo padrão de Admin\ExportController::download().
        \App\Models\AccessLog::record(
            action: \App\Models\AccessLog::ACTION_PATIENT_PAYMENTS_EXPORTED,
            description: "Pagamentos exportados do paciente {$patient->nome} {$patient->sobrenome}",
            metadata: ['patient_id' => $patient->id, 'format' => $format],
        );

        if ($format === 'excel') {
            return Excel::download(new PatientPaymentsExport($payments, $exportService), "{$filename}.xlsx");
        }

        return $exportService->streamCsv($payments, "{$filename}.csv");
    }
}
