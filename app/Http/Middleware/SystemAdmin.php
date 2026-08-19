<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Substitui o antigo middleware SuperAdmin (e-mail hardcoded). Fonte de
 * verdade agora é a tabela system_admins (via User::isSystemAdmin()) —
 * suporta múltiplos administradores, nenhum é privilegiado por e-mail.
 * Deliberadamente independente de current_clinic_id/EnsureCurrentClinic:
 * System Admin é uma camada acima do nível de clínica.
 */
class SystemAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || ! Auth::user()->isSystemAdmin()) {
            abort(403, 'Acesso restrito a administradores da plataforma.');
        }

        return $next($request);
    }
}
