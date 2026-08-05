<?php

namespace App\Models;

use Laravel\Cashier\Subscription as CashierSubscription;

/**
 * Cashier's Stripe-specific subscription bookkeeping, stored in its own table
 * (stripe_subscriptions) to avoid colliding with the pre-existing domain-level
 * App\Models\Subscription state machine used across the rest of the app.
 */
class StripeSubscription extends CashierSubscription
{
    protected $table = 'stripe_subscriptions';
}
