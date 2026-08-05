<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->enum('status', ['trial', 'active', 'cancelled', 'expired', 'paused'])->default('trial');
            $table->enum('interval', ['monthly', 'yearly'])->default('monthly');
            $table->timestamp('trial_starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->string('gateway', 50)->nullable();
            $table->string('gateway_subscription_id')->nullable();
            $table->timestamps();

            $table->index('clinic_id');
            $table->index(['status', 'next_billing_at']);
        });

        Schema::create('subscription_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->enum('event', ['created', 'activated', 'cancelled', 'expired', 'upgraded', 'downgraded', 'renewed', 'paused', 'resumed']);
            $table->foreignId('plan_id_from')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('plan_id_to')->nullable()->constrained('plans')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled', 'refunded'])->default('pending');
            $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('gateway', 50)->nullable();
            $table->string('gateway_invoice_id')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
        });

        Schema::create('trials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ends_at');
            $table->boolean('is_extended')->default(false);
            $table->boolean('extended_by_referral')->default(false);
            $table->timestamps();

            $table->index('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trials');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscription_history');
        Schema::dropIfExists('subscriptions');
    }
};
