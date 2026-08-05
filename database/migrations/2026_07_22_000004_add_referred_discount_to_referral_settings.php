<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_settings', function (Blueprint $table) {
            // Desconto dado ao CONVIDADO na primeira mensalidade — distinto de
            // reward_amount, que é o bônus pago a quem indicou.
            $table->decimal('referred_discount_amount', 10, 2)->default(0)->after('reward_amount');
        });
    }

    public function down(): void
    {
        Schema::table('referral_settings', function (Blueprint $table) {
            $table->dropColumn('referred_discount_amount');
        });
    }
};
