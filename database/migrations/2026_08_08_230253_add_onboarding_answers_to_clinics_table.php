<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            // Respostas do onboarding — só para segmentação/contexto; a
            // quantidade de cadeiras NÃO é armazenada aqui, é sempre
            // derivada de COUNT(chairs) (ver Chair model).
            $table->string('onboarding_stage', 30)->nullable()->after('type');
            $table->string('onboarding_current_system', 30)->nullable()->after('onboarding_stage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['onboarding_stage', 'onboarding_current_system']);
        });
    }
};
