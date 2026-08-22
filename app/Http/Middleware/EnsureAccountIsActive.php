<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    // Bloqueio real de conta (status='inativo') acontece aqui, não no login
    // (ver LoginRequest::authenticate) — a conta autentica normalmente, mas
    // toda rota autenticada cai aqui em vez de rodar o controller de
    // verdade, então nenhum dado real da clínica chega a ser enviado ao
    // front. GET normal (navegação Inertia) mostra a tela de aviso; qualquer
    // outro método, ou uma chamada que espera JSON puro (fora do fluxo
    // Inertia), é recusado com 403 — o bloqueio não depende do front decidir
    // não mostrar nada.
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || $user->status !== 'inativo') {
            return $next($request);
        }

        if (! $request->isMethod('GET') || $request->wantsJson()) {
            abort(403);
        }

        return Inertia::render('Auth/AccountBlocked')->toResponse($request);
    }
}
