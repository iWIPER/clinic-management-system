<?php

namespace App\Exports;

use App\Services\PatientPaymentExportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Consome PatientPaymentExportService::columns() — as mesmas colunas usadas
 * pela exportação CSV (PatientPaymentController::export) — para os dois
 * formatos nunca divergirem entre si.
 */
class PatientPaymentsExport implements FromArray, WithHeadings
{
    public function __construct(
        private Collection $payments,
        private PatientPaymentExportService $exportService,
    ) {}

    public function headings(): array
    {
        return $this->exportService->headings();
    }

    public function array(): array
    {
        return $this->exportService->rowsFor($this->payments);
    }
}
