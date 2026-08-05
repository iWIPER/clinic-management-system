<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::bind('anamnesis', fn (string $value) => \App\Models\AnamnesisInstance::findOrFail($value));

            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(base_path('routes/auth.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'clinic'      => \App\Http\Middleware\EnsureCurrentClinic::class,
            'super-admin' => \App\Http\Middleware\SuperAdmin::class,
            'affiliate'   => \App\Http\Middleware\EnsureAffiliate::class,
        ]);

        // Webhooks são chamados pelo Stripe, sem sessão/cookie CSRF do app.
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
