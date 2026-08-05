<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAffiliate
{
    /**
     * Restringe o grupo de rotas /afiliado a contas Affiliate — o inverso de
     * EnsureCurrentClinic, que já desvia Affiliates das rotas clínicas.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || ! Auth::user()->isAffiliate()) {
            abort(403, 'Acesso restrito a contas de afiliado.');
        }

        return $next($request);
    }
}
