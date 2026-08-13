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
            // Horário de atendimento do profissional NESTA clínica — mesmo
            // espírito de working_days: string simples "HH:MM" (não um
            // TIME/DATETIME nativo), pra nunca sofrer conversão de fuso
            // horário na leitura/escrita. Nulo = ainda não configurado;
            // ClinicUserPivot::workingHoursResolved() resolve pro default
            // (09:00–18:00), igual working_days resolve pra "todos os dias".
            $table->string('working_start', 5)->nullable()->after('working_days');
            $table->string('working_end', 5)->nullable()->after('working_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinic_user', function (Blueprint $table) {
            $table->dropColumn(['working_start', 'working_end']);
        });
    }
};
