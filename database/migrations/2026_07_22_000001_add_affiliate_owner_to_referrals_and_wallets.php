<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Referral/ReferralWallet can now be owned by a Clinic (existing behavior,
     * unchanged) OR by a standalone affiliate User — never both. clinic_id just
     * becomes nullable so every existing clinic-owned call site keeps working
     * unchanged; affiliate_user_id is the new, purely additive path. MySQL unique
     * indexes already allow multiple NULLs, so the existing unique(clinic_id)
     * constraint needs no change.
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id')->nullable()->change();
            $table->foreignId('affiliate_user_id')->nullable()->after('clinic_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('referral_wallets', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id')->nullable()->change();
            $table->foreignId('affiliate_user_id')->nullable()->after('clinic_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('referral_wallets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_user_id');
            $table->unsignedBigInteger('clinic_id')->nullable(false)->change();
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_user_id');
            $table->unsignedBigInteger('clinic_id')->nullable(false)->change();
        });
    }
};
