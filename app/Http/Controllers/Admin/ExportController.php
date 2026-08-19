<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Services\Admin\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function index(): \Inertia\Response
    {
        return Inertia::render('Admin/Exports/Index', [
            'datasets' => collect(ExportService::DATASETS)
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
                ->values(),
        ]);
    }

    public function download(Request $request, string $dataset, ExportService $service): StreamedResponse
    {
        abort_unless(array_key_exists($dataset, ExportService::DATASETS), 404);

        $filters = $request->only(['status', 'from', 'to', 'action', 'clinic_id']);

        // Toda exportação administrativa é auditada — dataset, filtros e
        // quem pediu, antes mesmo do streaming começar (não depois: se o
        // download for interrompido no meio, ainda queremos o registro de
        // que foi solicitado).
        AccessLog::record(
            action: 'admin_export_downloaded',
            description: 'Exportação administrativa: ' . ExportService::DATASETS[$dataset],
            metadata: ['dataset' => $dataset, 'filters' => array_filter($filters)],
        );

        return $service->stream($dataset, $filters);
    }
}
