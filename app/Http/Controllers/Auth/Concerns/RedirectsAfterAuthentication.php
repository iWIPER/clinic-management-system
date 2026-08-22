<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

trait RedirectsAfterAuthentication
{
    // Compartilhado entre login por senha e login social: System Admin vai
    // direto pro Backoffice, usuário com clínica cai no dashboard com o
    // contexto de clínica já na sessão, e quem ainda não tem clínica vai pro
    // onboarding. Extraído para não divergir entre os dois fluxos de login.
    protected function redirectAfterAuthentication(User $user): RedirectResponse
    {
        if ($user->isSystemAdmin()) {
            return redirect()->intended(route('admin.index'));
        }

        $clinic = $user->clinics()->first();

        if ($clinic) {
            session(['current_clinic_id' => $clinic->id]);
            session(['current_clinic' => $clinic->toSessionPayload()]);

            return redirect()->intended(route('dashboard'));
        }

        return redirect()->route('onboarding.choose-role');
    }
}
