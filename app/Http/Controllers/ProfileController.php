<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Rules\ValidCpf;
use App\Rules\ValidCro;
use App\Services\UserAvatarService;
use App\Services\UserProfileService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private UserProfileService $profileService,
        private UserAvatarService $avatarService,
    ) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $clinicId = $request->session()->get('current_clinic_id');

        return Inertia::render('Profile/Edit', [
            'profile'         => $this->profileService->toPageData($user, $clinicId),
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status'          => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $isPasswordOnly = $request->has('current_password')
            && ! $request->has('name')
            && ! $request->has('email');

        if ($isPasswordOnly) {
            return $this->updatePassword($request);
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone'       => ['nullable', 'string', 'max:20'],
            'cpf'         => ['nullable', 'string', 'max:14', new ValidCpf],
            'birth_date'  => ['nullable', 'date', 'before:today'],
            'gender'      => ['nullable', Rule::in(['masculino', 'feminino', 'outro', 'prefiro_nao_informar'])],
            'cro'         => ['nullable', 'string', 'max:10', new ValidCro],
            'cro_uf'      => ['nullable', 'string', 'size:2'],
            'specialty'   => ['nullable', 'string', 'max:150'],
            'job_title'   => ['nullable', Rule::in(Invite::JOB_TITLES)],
            'photo'       => ['nullable', 'image', 'max:2048'],
        ]);

        $original = $user->only([
            'name', 'email', 'phone', 'cpf', 'birth_date', 'gender',
            'cro', 'cro_uf', 'specialty', 'job_title',
        ]);

        $cro = $validated['cro'] ?? null;

        // O cargo define regras do sistema (elegibilidade clínica, permissões) —
        // só quem é owner/admin da clínica atual pode alterá-lo, inclusive o
        // próprio. Demais usuários: valor enviado é ignorado, mantém o atual.
        $canEditJobTitle = $user->can('manageTeam', $user->currentClinic());

        $user->fill([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'phone'      => $validated['phone'] ?? null,
            'cpf'        => $validated['cpf'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'gender'     => $validated['gender'] ?? null,
            'cro'        => $cro ? preg_replace('/\D/', '', $cro) : null,
            'cro_uf'     => ! empty($validated['cro_uf']) ? strtoupper($validated['cro_uf']) : null,
            'specialty'  => $validated['specialty'] ?? null,
            'job_title'  => $canEditJobTitle ? ($validated['job_title'] ?? null) : $user->job_title,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('photo')) {
            $path = $this->avatarService->store($user, $request->file('photo'));
            $user->profile_photo_path = $path;
            $this->profileService->logChange($user, 'Foto alterada', 'profile_photo_path', $request);
        }

        $hasChanges = $user->isDirty([
            'name', 'email', 'phone', 'cpf', 'birth_date', 'gender',
            'cro', 'cro_uf', 'specialty', 'job_title', 'profile_photo_path',
        ]);

        if ($hasChanges) {
            $user->profile_updated_at = now();
        }

        $user->save();

        $this->profileService->logProfileChanges($user, $original, $user->only([
            'name', 'email', 'phone', 'cpf', 'birth_date', 'gender',
            'cro', 'cro_uf', 'specialty', 'job_title',
        ]), $request);

        return back()->with('status', 'Perfil atualizado com sucesso.');
    }

    public function updateQuickActions(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'quick_actions'   => ['present', 'array', 'max:2'],
            'quick_actions.*' => ['string', Rule::in(UserProfileService::ALLOWED_QUICK_ACTIONS)],
        ]);

        $user = $request->user();
        $user->preferences = array_merge(
            $this->profileService->defaultPreferences(),
            $user->preferences ?? [],
            ['quick_actions' => array_values(array_unique($validated['quick_actions']))],
        );
        $user->save();

        return back()->with('status', 'Ações rápidas atualizadas.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->password = Hash::make($validated['password']);
        $user->profile_updated_at = now();
        $user->save();

        $this->profileService->logChange($user, 'Senha alterada', 'password', $request);

        \App\Models\AccessLog::record(
            action: \App\Models\AccessLog::ACTION_PASSWORD_CHANGED,
            userId: $user->id,
        );

        return back()->with('status', 'Senha alterada com sucesso.');
    }

    public function removePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->avatarService->remove($user);
        $user->profile_updated_at = now();
        $user->save();

        $this->profileService->logChange($user, 'Foto removida', 'profile_photo_path', $request);

        return back()->with('status', 'Foto removida com sucesso.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}