<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessLogController extends Controller
{
    public function index(Request $request)
    {
        $clinicId = session('current_clinic_id');

        // Filtro de período
        $range = $request->get('range', 'today');
        $from  = match ($range) {
            '7days'  => now()->subDays(7)->startOfDay(),
            '30days' => now()->subDays(30)->startOfDay(),
            default  => now()->startOfDay(), // 'today'
        };

        $query = AccessLog::where('clinic_id', $clinicId)
            ->where('created_at', '>=', $from)
            ->with('user:id,name,email');

        // Pesquisa por texto
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ilike', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', "%{$search}%"));
            });
        }

        $logs = $query->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'id'           => $log->id,
                'action'       => $log->action,
                'action_label' => $log->action_label,
                'description'  => $log->description,
                'ip_address'   => $log->ip_address,
                'device_type'  => $log->device_type,
                'browser'      => $log->browser,
                'os'           => $log->os,
                'city'         => $log->city,
                'country'      => $log->country,
                'created_at'   => $log->created_at,
                'user'         => $log->user ? [
                    'id'    => $log->user->id,
                    'name'  => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
            ]);

        return Inertia::render('AccessLogs/Index', [
            'logs'   => $logs,
            'range'  => $range,
            'search' => $search ?? '',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $clinicId = session('current_clinic_id');

        $range = $request->get('range', '30days');
        $from  = match ($range) {
            '7days'  => now()->subDays(7)->startOfDay(),
            '30days' => now()->subDays(30)->startOfDay(),
            'today'  => now()->startOfDay(),
            default  => now()->subDays(30)->startOfDay(),
        };

        $logs = AccessLog::where('clinic_id', $clinicId)
            ->where('created_at', '>=', $from)
            ->with('user:id,name,email')
            ->latest('created_at')
            ->get();

        $filename = 'logs-acesso-' . now()->format('Y-m-d') . '.csv';

        // Auditado antes do streaming começar (não depois: se o download for
        // interrompido no meio, ainda queremos o registro de que foi pedido)
        // — mesmo padrão de Admin\ExportController::download().
        AccessLog::record(
            action: AccessLog::ACTION_ACCESS_LOG_EXPORTED,
            description: 'Logs de acesso exportados',
            metadata: ['range' => $range],
        );

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($handle, ['Data/Hora', 'Usuário', 'E-mail', 'Ação', 'Descrição',
                              'IP', 'Dispositivo', 'Navegador', 'SO', 'Cidade', 'País']);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->created_at->format('d/m/Y H:i:s'),
                    $log->user?->name ?? '-',
                    $log->user?->email ?? '-',
                    $log->action_label,
                    $log->description,
                    $log->ip_address ?? '-',
                    ucfirst($log->device_type),
                    $log->browser ?? '-',
                    $log->os ?? '-',
                    $log->city ?? '-',
                    $log->country ?? '-',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
