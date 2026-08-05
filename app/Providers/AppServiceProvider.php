<?php

namespace App\Providers;

use App\Contracts\Signature\SignatureAdapterInterface;
use App\Listeners\LogAuthEvents;
use App\Models\StripeSubscription;
use App\Models\StripeSubscriptionItem;
use App\Services\Signature\AutentiqueAdapter;
use App\Services\Signature\NullSignatureAdapter;
use App\Services\Signature\ZapSignAdapter;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SignatureAdapterInterface::class, function () {
            return match (config('services.signature.driver', 'null')) {
                'zapsign' => new ZapSignAdapter(),
                'autentique' => new AutentiqueAdapter(),
                default => new NullSignatureAdapter(),
            };
        });
    }

    public function boot(): void
    {
        Event::listen(Login::class,  [LogAuthEvents::class, 'handleLogin']);
        Event::listen(Logout::class, [LogAuthEvents::class, 'handleLogout']);

        Cashier::useSubscriptionModel(StripeSubscription::class);
        Cashier::useSubscriptionItemModel(StripeSubscriptionItem::class);
    }
}