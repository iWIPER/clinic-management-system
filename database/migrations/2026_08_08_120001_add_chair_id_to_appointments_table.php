<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Nullable de propósito — agendamentos existentes nascem sem
            // cadeira (nenhum dado é inventado/adivinhado) e continuam
            // funcionando normalmente na interface como "Sem cadeira".
            // nullOnDelete: excluir uma cadeira nunca apaga o agendamento,
            // só desvincula (mesma rede de segurança de treatment_id).
            $table->foreignId('chair_id')->nullable()->after('treatment_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chair_id');
        });
    }
};
