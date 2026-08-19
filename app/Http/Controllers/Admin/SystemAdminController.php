<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemAdmin;
use App\Models\User;
use App\Services\SystemAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SystemAdminController extends Controller
{
    public function index(): \Inertia\Response
    {
        $admins = SystemAdmin::active()
            ->with(['user:id,name,email', 'grantedBy:id,name'])
            ->orderBy('granted_at')
            ->get()
            ->map(fn ($a) => [
                'id'          => $a->id,
                'user'        => ['id' => $a->user->id, 'name' => $a->user->name, 'email' => $a->user->email],
                'granted_at'  => $a->granted_at->toISOString(),
                'granted_by'  => $a->grantedBy?->name ?? 'Bootstrap (linha de comando)',
                'is_self'     => $a->user_id === Auth::id(),
            ]);

        return Inertia::render('Admin/SystemAdmins/Index', [
            'admins' => $admins,
        ]);
    }

    public function store(Request $request, SystemAdminService $service): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $target = User::where('email', $validated['email'])->first();

        if (! $target) {
            throw ValidationException::withMessages(['email' => 'Nenhum usuário encontrado com este e-mail.']);
        }

        $service->grant($target, Auth::user());

        return response()->json(['ok' => true]);
    }

    public function destroy(User $user, SystemAdminService $service): \Illuminate\Http\JsonResponse
    {
        $service->revoke($user, Auth::user());

        return response()->json(['ok' => true]);
    }
}
