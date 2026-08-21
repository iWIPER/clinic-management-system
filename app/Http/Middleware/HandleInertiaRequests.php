<?php

namespace App\Http\Middleware;

use App\Models\Clinic;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'isSystemAdmin' => fn () => (bool) $request->user()?->isSystemAdmin(),
                'isAffiliate' => fn () => (bool) $request->user()?->isAffiliate(),
                // Clínicas onde o próprio System Admin é membro real
                // (clinic_user) — só essas podem virar "Entrar na clínica"
                // no Backoffice (ver Admin\ClinicController::enter()).
                // Vazio pra quem não é System Admin, sem custo extra de
                // query pro caso comum.
                'myClinics' => function () use ($request) {
                    $user = $request->user();

                    if (! $user?->isSystemAdmin()) {
                        return [];
                    }

                    return $user->clinics()
                        ->select('clinics.id', 'clinics.name')
                        ->get()
                        ->map(fn ($clinic) => ['id' => $clinic->id, 'name' => $clinic->name]);
                },
                // Aviso de acesso privilegiado ao /admin (puramente informativo,
                // nunca autorização) — persistido em users.preferences (não
                // sessão/localStorage) porque deve aparecer só uma vez por
                // usuário, nunca de novo em logout/login ou sessão nova.
                'hasAcknowledgedAdminAccess' => fn () => (bool) ($request->user()?->preferences['admin_notice_acknowledged_at'] ?? null),
            ],
            'flash' => [
                'success'                    => fn () => $request->session()->get('success'),
                'error'                      => fn () => $request->session()->get('error'),
                'disaster_recovery_required' => fn () => (bool) $request->session()->get('disaster_recovery_required'),
            ],
            'currentClinic' => function () use ($request) {
                $clinicId = $request->session()->get('current_clinic_id');

                if (! $clinicId) {
                    return null;
                }

                $clinic = Clinic::find($clinicId);

                return $clinic?->toSessionPayload();
            },
            // Chaves das até 2 ações personalizadas da TopIsland (ver
            // UserProfileService::ALLOWED_QUICK_ACTIONS). Compartilhado
            // globalmente porque a TopIsland aparece em toda página do
            // modo clínica, não só na tela de perfil.
            'quickActions' => fn () => $request->user()?->preferences['quick_actions'] ?? [],
        ];
    }
}
