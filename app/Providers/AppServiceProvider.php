<?php

namespace App\Providers;

use App\Listeners\LogAuthEvents;
use App\Models\StripeSubscription;
use App\Models\StripeSubscriptionItem;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Event::listen(Login::class,  [LogAuthEvents::class, 'handleLogin']);
        Event::listen(Logout::class, [LogAuthEvents::class, 'handleLogout']);
        Event::listen(Failed::class, [LogAuthEvents::class, 'handleFailed']);

        Cashier::useSubscriptionModel(StripeSubscription::class);
        Cashier::useSubscriptionItemModel(StripeSubscriptionItem::class);
    }
}