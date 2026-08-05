<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SuperAdmin
{
    const EMAIL = 'lellis.joseanesl@gmail.com';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || Auth::user()->email !== self::EMAIL) {
            abort(403, 'Acesso restrito ao super-administrador.');
        }

        return $next($request);
    }
}
