<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_conversions', function (Blueprint $table) {
            $table->enum('status', [
                'testing',
                'awaiting_payment',
                'payment_confirmed',
                'eligible',
                'paid',
                'cancelled',
                'expired',
                'refunded',
                'under_review',
            ])->default('testing')->change();
        });
    }

    public function down(): void
    {
        Schema::table('referral_conversions', function (Blueprint $table) {
            $table->enum('status', [
                'testing',
                'awaiting_payment',
                'payment_confirmed',
                'eligible',
                'paid',
                'cancelled',
                'expired',
            ])->default('testing')->change();
        });
    }
};
