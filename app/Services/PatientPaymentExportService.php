<?php

namespace App\Services;

use App\Models\PatientPayment;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Única fonte das colunas de exportação de pagamentos — CSV (streamCsv) e
 * Excel (App\Exports\PatientPaymentsExport) consomem exatamente a mesma
 * definição de columns(), mesmo padrão de PatientExportService.
 */
class PatientPaymentExportService
{
    /**
     * @return array<string, callable(PatientPayment): string>
     */
    public function columns(): array
    {
        return [
            'Tratamento' => fn (PatientPayment $p) => $p->treatment?->procedure_name ?? '—',
            'Parcela' => fn (PatientPayment $p) => "{$p->installment_number}/{$p->installment_total}",
            'Vencimento' => fn (PatientPayment $p) => $p->due_date?->format('d/m/Y') ?? 'Não informado',
            'Valor' => fn (PatientPayment $p) => number_format((float) $p->amount, 2, ',', '.'),
            'Desconto' => fn (PatientPayment $p) => number_format((float) $p->discount, 2, ',', '.'),
            'Juros' => fn (PatientPayment $p) => number_format((float) $p->interest, 2, ',', '.'),
            'Valor recebido' => fn (PatientPayment $p) => number_format((float) $p->amount_paid, 2, ',', '.'),
            'Status' => fn (PatientPayment $p) => PatientPayment::STATUSES[$p->status] ?? $p->status,
            'Forma de pagamento' => fn (PatientPayment $p) => PatientPayment::METHODS[$p->payment_method] ?? 'Não informado',
            'Recebido em' => fn (PatientPayment $p) => $p->paid_at?->format('d/m/Y H:i') ?? 'Não recebido',
        ];
    }

    public function headings(): array
    {
        return array_keys($this->columns());
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function rowsFor(Collection $payments): array
    {
        $columns = $this->columns();

        return $payments->map(fn (PatientPayment $p) => collect($columns)
            ->map(fn (callable $resolve) => $resolve($p))
            ->values()
            ->all()
        )->all();
    }

    public function streamCsv(Collection $payments, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($payments) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($handle, $this->headings());

            foreach ($this->rowsFor($payments) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
