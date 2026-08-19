<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\User;
use App\Services\UserRemovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $query = User::query()
            ->when($request->search, function ($q, $s) {
                // LOWER()+LIKE em vez de ilike (Postgres-only) — mesmo
                // resultado de busca case-insensitive, mas portável pro
                // SQLite usado nos testes.
                $term = '%' . mb_strtolower($s) . '%';
                $q->where(fn ($q2) => $q2
                    ->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]));
            })
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->withCount('clinics')
            ->latest();

        $users = $query->paginate(30)->through(fn ($u) => [
            'id'             => $u->id,
            'name'           => $u->name,
            'email'          => $u->email,
            'status'         => $u->status ?? 'ativo',
            'is_system_admin' => $u->isSystemAdmin(),
            'clinics_count'  => $u->clinics_count,
            'last_login_at'  => $u->last_login_at,
            'created_at'     => $u->created_at->toISOString(),
        ]);

        return Inertia::render('Admin/Users/Index', [
            'users'   => $users,
            'filters' => ['search' => $request->search, 'status' => $request->status],
        ]);
    }

    public function show(User $user): \Inertia\Response
    {
        $clinics = $user->clinics()
            ->select('clinics.id', 'clinics.name', 'clinics.trade_name', 'clinics.status')
            ->withPivot('role', 'created_at as joined_at')
            ->get()
            ->map(fn ($c) => [
                'id'        => $c->id,
                'name'      => $c->trade_name ?? $c->name,
                'status'    => $c->status,
                'role'      => $c->pivot->role,
                'joined_at' => $c->pivot->joined_at,
            ]);

        $recentActivity = AccessLog::where('user_id', $user->id)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($log) => [
                'id'           => $log->id,
                'action_label' => $log->action_label,
                'description'  => $log->description,
                'created_at'   => $log->created_at->toISOString(),
            ]);

        return Inertia::render('Admin/Users/Show', [
            'targetUser' => [
                'id'              => $user->id,
                'name'            => $user->name,
                'email'           => $user->email,
                'phone'           => $user->phone,
                'status'          => $user->status ?? 'ativo',
                'is_system_admin' => $user->isSystemAdmin(),
                'last_login_at'   => $user->last_login_at,
                'created_at'      => $user->created_at->toISOString(),
            ],
            'clinics'         => $clinics,
            'recent_activity' => $recentActivity,
        ]);
    }

    public function block(User $user): \Illuminate\Http\JsonResponse
    {
        abort_if($user->id === Auth::id(), 403, 'Não é possível bloquear sua própria conta.');

        $user->update(['status' => 'inativo']);

        AccessLog::record(
            action: 'admin_user_blocked',
            description: "Usuário {$user->name} bloqueado pelo administrador da plataforma",
            metadata: ['target_user_id' => $user->id],
        );

        return response()->json(['ok' => true]);
    }

    public function unblock(User $user): \Illuminate\Http\JsonResponse
    {
        $user->update(['status' => 'ativo']);

        AccessLog::record(
            action: 'admin_user_unblocked',
            description: "Usuário {$user->name} desbloqueado pelo administrador da plataforma",
            metadata: ['target_user_id' => $user->id],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, User $user, UserRemovalService $service): \Illuminate\Http\JsonResponse
    {
        abort_if($user->id === Auth::id(), 403, 'Não é possível excluir sua própria conta por aqui.');

        $request->validate([
            'confirmation' => 'required|string',
        ]);

        if ($request->input('confirmation') !== $user->email) {
            throw ValidationException::withMessages(['confirmation' => 'Digite o e-mail exato do usuário para confirmar a exclusão.']);
        }

        $result = $service->remove($user, Auth::user());

        return response()->json(['ok' => true, 'result' => $result]);
    }
}
