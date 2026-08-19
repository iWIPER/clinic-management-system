<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Clinic;
use App\Models\Referral;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Fase System Admin/Backoffice — extraído de Admin\DashboardController
 * (que acumulava clínicas + planos + billing + indicações + logs na mesma
 * classe, achado RC-16 da auditoria C0). Comportamento de index/block/
 * unblock preservado byte-a-byte; show() é novo.
 */
class ClinicController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $query = Clinic::with(['subscription.plan'])
            ->when($request->search, function ($q, $s) {
                // Achado incidental (não introduzido nesta fase): o
                // DashboardController original usava ilike (Postgres-only)
                // aqui, nunca coberto por teste antes — falha em SQLite.
                // LOWER()+LIKE dá o mesmo resultado, portável.
                $term = '%' . mb_strtolower($s) . '%';
                $q->where(fn ($q2) => $q2
                    ->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(trade_name) LIKE ?', [$term]));
            })
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest();

        $clinics = $query->paginate(25)->through(fn ($c) => [
            'id'            => $c->id,
            'name'          => $c->trade_name ?? $c->name,
            'created_at'    => $c->created_at->format('d/m/Y'),
            'plan'          => $c->subscription?->plan?->name ?? 'Sem plano',
            'status'        => $c->status,
            'subscription_status' => $c->subscription?->status ?? 'sem_assinatura',
            'referral_code' => Referral::where('clinic_id', $c->id)->value('code'),
        ]);

        return Inertia::render('Admin/Clinics/Index', [
            'clinics' => $clinics,
            'filters' => ['search' => $request->search, 'status' => $request->status],
        ]);
    }

    public function show(Clinic $clinic): \Inertia\Response
    {
        $clinic->load(['subscription.plan']);

        $owner = $clinic->owner();

        $members = $clinic->users()
            ->select('users.id', 'users.name', 'users.email', 'users.status', 'users.last_login_at')
            ->withPivot('role', 'created_at as joined_at')
            ->orderBy('clinic_user.created_at')
            ->get()
            ->map(fn ($u) => [
                'id'            => $u->id,
                'name'          => $u->name,
                'email'         => $u->email,
                'status'        => $u->status ?? 'ativo',
                'role'          => $u->pivot->role,
                'joined_at'     => $u->pivot->joined_at,
                'last_login_at' => $u->last_login_at,
            ]);

        $recentActivity = AccessLog::where('clinic_id', $clinic->id)
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($log) => [
                'id'           => $log->id,
                'action_label' => $log->action_label,
                'description'  => $log->description,
                'user'         => $log->user?->name ?? '—',
                'created_at'   => $log->created_at->toISOString(),
            ]);

        return Inertia::render('Admin/Clinics/Show', [
            'clinic' => [
                'id'           => $clinic->id,
                'name'         => $clinic->name,
                'trade_name'   => $clinic->trade_name,
                'status'       => $clinic->status,
                'type'         => $clinic->type,
                'created_at'   => $clinic->created_at->toISOString(),
                'owner'        => $owner ? ['id' => $owner->id, 'name' => $owner->name, 'email' => $owner->email] : null,
                'plan'         => $clinic->subscription?->plan?->name ?? 'Sem plano',
                'subscription_status' => $clinic->subscription?->status ?? 'sem_assinatura',
                'trial_ends_at' => $clinic->subscription?->trial_ends_at?->toISOString(),
                'next_billing_at' => $clinic->subscription?->next_billing_at?->toISOString(),
                'members_count' => $members->count(),
                'patients_count' => $clinic->patients()->count(),
            ],
            'members'         => $members,
            'recent_activity' => $recentActivity,
        ]);
    }

    public function block(Clinic $clinic): \Illuminate\Http\JsonResponse
    {
        $clinic->update(['status' => 'suspended']);

        AccessLog::record(
            action: 'admin_clinic_blocked',
            description: "Clínica {$clinic->displayName()} bloqueada pelo administrador da plataforma",
            metadata: ['clinic_id' => $clinic->id],
        );

        return response()->json(['ok' => true]);
    }

    public function unblock(Clinic $clinic): \Illuminate\Http\JsonResponse
    {
        $clinic->update(['status' => 'active']);

        AccessLog::record(
            action: 'admin_clinic_unblocked',
            description: "Clínica {$clinic->displayName()} desbloqueada pelo administrador da plataforma",
            metadata: ['clinic_id' => $clinic->id],
        );

        return response()->json(['ok' => true]);
    }
}
