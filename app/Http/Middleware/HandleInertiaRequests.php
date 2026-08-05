<?php

namespace App\Http\Middleware;

use App\Models\Clinic;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

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
                'isSuperAdmin' => fn () => $request->user()
                    && $request->user()->email === \App\Http\Middleware\SuperAdmin::EMAIL,
                'isAffiliate' => fn () => (bool) $request->user()?->isAffiliate(),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
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
        ];
    }
}
