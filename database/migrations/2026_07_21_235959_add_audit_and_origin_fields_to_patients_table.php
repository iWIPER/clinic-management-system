<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Colunas mínimas de que PatientInviteService::resolvePatient() precisa
     * para criar o paciente ao gerar um convite: quem criou/atualizou o
     * registro e o profissional responsável (mesma auto-atribuição por
     * job_title de PatientController::store()), além de 'origem' para marcar
     * 'convite'. Extraído deliberadamente de uma migration maior e ainda não
     * commitada ("Visão Geral do Paciente"), que também adiciona a coluna
     * legada 'convenio' (texto) e roda um backfill histórico — nenhum dos
     * dois é usado por este módulo.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('responsible_professional_id')->nullable()->after('drive_folder_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->after('responsible_professional_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->after('created_by_id')
                ->constrained('users')->nullOnDelete();
            $table->string('origem', 30)->nullable()->after('observacoes');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_professional_id');
            $table->dropConstrainedForeignId('created_by_id');
            $table->dropConstrainedForeignId('updated_by_id');
            $table->dropColumn('origem');
        });
    }
};
