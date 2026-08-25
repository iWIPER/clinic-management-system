<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventBrowserCaching
{
    // Sem isso, o navegador pode restaurar /login (ou o 302 de
    // /login/google/redirect) do back-forward cache ao voltar da tela de
    // consentimento do Google — a página fica "congelada" e o botão
    // "Continuar com Google" para de disparar navegação até a aba ser
    // recarregada. no-store tira essas respostas da elegibilidade de
    // bfcache/cache HTTP.
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
