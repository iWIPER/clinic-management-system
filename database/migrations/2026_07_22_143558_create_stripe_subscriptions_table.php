<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Cashier's own subscription bookkeeping (Stripe-specific fields), kept in
     * a separate table from `subscriptions` (App\Models\Subscription, the
     * pre-existing domain-level state machine used across the app). The FK is
     * clinic_id because Billable is attached to Clinic, not User.
     */
    public function up(): void
    {
        Schema::create('stripe_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id');
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'stripe_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_subscriptions');
    }
};
