<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            // Horário de funcionamento por dia da semana (mon..sun, mesmas
            // chaves de ClinicUserPivot::DAY_KEYS) — regra administrativa
            // que pode restringir a configuração individual do profissional
            // (ver Clinic::businessHoursFor/businessHoursEnforced). Nulo =
            // clínica nunca configurou -> nenhuma restrição, comportamento
            // idêntico ao que já existia antes desta coluna.
            $table->json('business_hours')->nullable()->after('settings');
            // Sem isto, business_hours funcionaria só como sugestão/
            // referência visual, nunca como limite de verdade.
            $table->boolean('business_hours_enforced')->default(false)->after('business_hours');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['business_hours', 'business_hours_enforced']);
        });
    }
};
