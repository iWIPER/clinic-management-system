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
        Schema::table('clinic_user', function (Blueprint $table) {
            // Se outros membros da clínica podem ver a agenda deste
            // profissional — independente de quais dias ele atende.
            $table->boolean('agenda_visible_to_team')->default(true)->after('role');

            // Dias da semana em que o profissional atende, NESTA clínica
            // (por vínculo, não por usuário — o mesmo profissional pode ter
            // dias diferentes em clínicas diferentes). Nulo = todos os dias
            // ligados (preserva o comportamento atual pra quem já existe).
            // Formato: {"mon":true,"tue":true,"wed":true,"thu":true,"fri":true,"sat":false,"sun":false}
            $table->json('working_days')->nullable()->after('agenda_visible_to_team');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinic_user', function (Blueprint $table) {
            $table->dropColumn(['agenda_visible_to_team', 'working_days']);
        });
    }
};
