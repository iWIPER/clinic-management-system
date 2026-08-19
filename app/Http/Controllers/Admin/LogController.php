<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Fase System Admin/Backoffice — extraído de Admin\DashboardController
 * (RC-16) e evoluído: além do range+busca que já existia, agora filtra
 * também por admin (user_id), ação e clínica, como pedido na seção 12.
 * "Filtrar por entidade" não foi implementado à parte — AccessLog não tem
 * coluna de entity_type/entity_id, só metadata (JSON não indexado); o
 * filtro por ação já cobre a maior parte do mesmo caso de uso na prática
 * atual (cada ação sensível já tem sua própria constante).
 */
class LogController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $range = $request->get('range', '7days');
        $from  = match ($range) {
            'today'  => now()->startOfDay(),
            '30days' => now()->subDays(30)->startOfDay(),
            'all'    => null,
            default  => now()->subDays(7)->startOfDay(),
        };

        $query = AccessLog::with(['user:id,name,email', 'clinic:id,name']);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ilike', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($clinicId = $request->get('clinic_id')) {
            $query->where('clinic_id', $clinicId);
        }

        $logs = $query->latest('created_at')->paginate(50)->through(fn ($log) => [
            'id'           => $log->id,
            'action'       => $log->action,
            'action_label' => $log->action_label,
            'description'  => $log->description,
            'ip_address'   => $log->ip_address,
            'browser'      => $log->browser,
            'user'         => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
            'clinic'       => $log->clinic ? ['id' => $log->clinic->id, 'name' => $log->clinic->name] : null,
            'created_at'   => $log->created_at->toISOString(),
            'metadata'     => $log->metadata,
        ]);

        return Inertia::render('Admin/Logs/Index', [
            'logs'    => $logs,
            'filters' => [
                'range'     => $range,
                'search'    => $search ?? '',
                'action'    => $action ?? '',
                'user_id'   => $userId ?? '',
                'clinic_id' => $clinicId ?? '',
            ],
            'action_options' => AccessLog::LABELS,
        ]);
    }
}
