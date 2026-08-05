<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\Clinic;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index()
    {
        $clinicId = session('current_clinic_id');
        $clinic   = Clinic::findOrFail($clinicId);

        $members = $clinic->users()
            ->select(
                'users.id', 'users.name', 'users.email', 'users.phone',
                'users.job_title', 'users.status', 'users.last_login_at',
                'users.profile_photo_path', 'users.created_at as user_created_at',
            )
            ->withPivot('role', 'created_at as joined_at')
            ->orderBy('clinic_user.created_at')
            ->get()
            ->map(fn ($u) => [
                'id'                  => $u->id,
                'name'                => $u->name,
                'email'               => $u->email,
                'phone'               => $u->phone,
                'job_title'           => $u->job_title,
                'status'              => $u->status ?? 'ativo',
                'last_login_at'       => $u->last_login_at,
                'profile_photo_path'  => $u->profile_photo_path,
                'role'                => $u->pivot->role,
                'joined_at'           => $u->pivot->joined_at,
                'is_current_user'     => $u->id === auth()->id(),
            ]);

        $pendingInvites = Invite::where('clinic_id', $clinicId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with('invitedBy:id,name')
            ->latest()
            ->get()
            ->map(fn ($i) => [
                'id'          => $i->id,
                'name'        => $i->name,
                'email'       => $i->email,
                'job_title'   => $i->job_title,
                'role'        => $i->role,
                'short_token' => $i->short_token,
                'expires_at'  => $i->expires_at,
                'invited_by'  => $i->invitedBy?->name,
            ]);

        return Inertia::render('Team/Index', [
            'members'          => $members,
            'pendingInvites'   => $pendingInvites,
            'currentUserRole'  => auth()->user()->roleInCurrentClinic(),
            'jobTitles'        => Invite::JOB_TITLES,
        ]);
    }

    public function deactivate(User $user)
    {
        $clinicId = session('current_clinic_id');
        $this->authorizeAdmin($clinicId);
        abort_if($user->id === auth()->id(), 403, 'Não é possível desativar sua própria conta.');

        $user->update(['status' => 'inativo']);

        AccessLog::record(
            action: AccessLog::ACTION_MEMBER_DEACTIVATED,
            description: "Membro {$user->name} desativado",
            metadata: ['target_user_id' => $user->id],
        );

        return back()->with('success', "{$user->name} foi desativado.");
    }

    public function reactivate(User $user)
    {
        $clinicId = session('current_clinic_id');
        $this->authorizeAdmin($clinicId);

        $user->update(['status' => 'ativo']);

        AccessLog::record(
            action: AccessLog::ACTION_MEMBER_REACTIVATED,
            description: "Membro {$user->name} reativado",
            metadata: ['target_user_id' => $user->id],
        );

        return back()->with('success', "{$user->name} foi reativado.");
    }

    public function updateRole(Request $request, User $user)
    {
        $clinicId = session('current_clinic_id');
        $this->authorizeAdmin($clinicId);
        abort_if($user->id === auth()->id(), 403, 'Não é possível alterar seu próprio cargo.');

        $validated = $request->validate([
            'role'      => 'required|in:admin,professional,staff',
            'job_title' => ['nullable', Rule::in(Invite::JOB_TITLES)],
        ]);

        $clinic = Clinic::findOrFail($clinicId);
        $clinic->users()->updateExistingPivot($user->id, ['role' => $validated['role']]);

        if ($validated['job_title'] !== null) {
            $user->update(['job_title' => $validated['job_title']]);
        }

        return back()->with('success', 'Cargo atualizado com sucesso.');
    }

    private function authorizeAdmin(int $clinicId): void
    {
        $role = auth()->user()->roleInCurrentClinic();
        abort_unless(in_array($role, ['owner', 'admin']), 403, 'Acesso não autorizado.');
    }
}
