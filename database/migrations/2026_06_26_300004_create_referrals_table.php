<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->unsignedInteger('clicks_count')->default(0);
            $table->unsignedInteger('conversions_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('code');
        });

        Schema::create('referral_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('referred_clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['referral_id', 'created_at']);
        });

        Schema::create('referral_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->foreignId('referred_clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('reward_amount', 10, 2)->default(0);
            $table->enum('status', [
                'testing',
                'awaiting_payment',
                'payment_confirmed',
                'eligible',
                'paid',
                'cancelled',
                'expired',
            ])->default('testing');
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('plan_subscribed_at')->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->timestamp('eligible_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique('referred_clinic_id');
            $table->index(['referral_id', 'status']);
        });

        Schema::create('referral_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 10, 2)->default(0);
            $table->decimal('pending_balance', 10, 2)->default(0);
            $table->decimal('total_earned', 10, 2)->default(0);
            $table->decimal('total_withdrawn', 10, 2)->default(0);
            $table->timestamp('last_payment_at')->nullable();
            $table->string('pix_type', 20)->nullable();
            $table->string('pix_key')->nullable();
            $table->timestamps();
        });

        Schema::create('referral_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('referral_wallets')->cascadeOnDelete();
            $table->foreignId('referral_conversion_id')->nullable()->constrained('referral_conversions')->nullOnDelete();
            $table->enum('type', ['credit', 'debit', 'pending', 'released']);
            $table->decimal('amount', 10, 2);
            $table->string('description');
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
        });

        Schema::create('referral_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('referral_wallets')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('pix_type', 20);
            $table->string('pix_key');
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['wallet_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_payments');
        Schema::dropIfExists('referral_transactions');
        Schema::dropIfExists('referral_wallets');
        Schema::dropIfExists('referral_conversions');
        Schema::dropIfExists('referral_clicks');
        Schema::dropIfExists('referrals');
    }
};
