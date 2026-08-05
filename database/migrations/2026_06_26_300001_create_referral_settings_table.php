<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('reward_amount', 10, 2)->default(50.00);
            $table->decimal('minimum_withdraw', 10, 2)->default(100.00);
            $table->unsignedInteger('trial_days')->default(7);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        // Semente única de configuração
        DB::table('referral_settings')->insert([
            'reward_amount'   => 50.00,
            'minimum_withdraw'=> 100.00,
            'trial_days'      => 7,
            'enabled'         => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_settings');
    }
};
