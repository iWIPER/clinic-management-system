<?php

namespace App\Providers;

use App\Listeners\LogAuthEvents;
use App\Models\StripeSubscription;
use App\Models\StripeSubscriptionItem;
use App\Services\GoogleDriveAuthService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use SocialiteProviders\Apple\Provider as AppleProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fase C1.2.3 — GoogleDriveService, GoogleDriveCallExecutor e
        // GoogleDriveStructureService dependem todos de GoogleDriveAuthService.
        // Sem singleton, o container criava uma instância (e um Google_Client
        // configurado do zero) por dependência resolvida — 3-4 por request —
        // e useHttpClientForTesting() só alcançava uma delas, deixando as
        // outras com o client HTTP real. Uma instância compartilhada por
        // request é também o comportamento correto em produção: o
        // Google_Client não guarda estado por clínica, só a configuração
        // OAuth (client_id/secret/scopes), que não precisa ser reconstruída
        // mais de uma vez por request.
        $this->app->singleton(GoogleDriveAuthService::class);
    }

    public function boot(): void
    {
        Event::listen(Login::class, [LogAuthEvents::class, 'handleLogin']);
        Event::listen(Logout::class, [LogAuthEvents::class, 'handleLogout']);
        Event::listen(Failed::class, [LogAuthEvents::class, 'handleFailed']);

        Cashier::useSubscriptionModel(StripeSubscription::class);
        Cashier::useSubscriptionItemModel(StripeSubscriptionItem::class);

        // Drivers registrados sob nomes próprios ('google_login'/'apple_login'),
        // lendo config('services.google_login'|'apple_login') — não o driver
        // padrão 'google' do Socialite, que colidiria com o app OAuth do
        // Google Drive já configurado em config('services.google').
        Socialite::extend('google_login', function ($app) {
            return Socialite::buildProvider(GoogleProvider::class, $app['config']['services.google_login']);
        });

        Socialite::extend('apple_login', function ($app) {
            return Socialite::buildProvider(AppleProvider::class, $app['config']['services.apple_login']);
        });
    }
}
