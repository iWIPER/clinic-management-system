<?php

namespace App\Exports;

use App\Services\PatientExportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Consome PatientExportService::columns() — as mesmas colunas usadas pela
 * exportação CSV (PatientController::export) — para os dois formatos nunca
 * divergirem entre si.
 */
class PatientsExport implements FromArray, WithHeadings
{
    public function __construct(
        private Collection $patients,
        private PatientExportService $exportService,
    ) {}

    public function headings(): array
    {
        return $this->exportService->headings();
    }

    public function array(): array
    {
        return $this->exportService->rowsFor($this->patients);
    }
}
