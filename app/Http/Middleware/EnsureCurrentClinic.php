<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentClinic
{
    /**
     * Garante que há uma clínica ativa no contexto da sessão.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        $clinicId = session('current_clinic_id');

        if (!$clinicId) {
            // Tenta pegar a primeira clínica do usuário
            $firstClinic = $user->clinics()->first();
            if ($firstClinic) {
                session(['current_clinic_id' => $firstClinic->id]);
            }
        }

        // Aqui podemos adicionar verificação de entitlements depois

        return $next($request);
    }
}
