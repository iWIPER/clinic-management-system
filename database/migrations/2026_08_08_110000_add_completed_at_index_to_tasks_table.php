<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Cobre a visão "Concluídas" (status='done' + janela de dias) e
            // o painel "Controle" (status='done' + completed_at de hoje) sem
            // precisar escanear a tabela toda por clínica. O índice
            // (clinic_id, status) já existente continua servindo outras
            // consultas (ex.: contagem de não concluídas) — este é aditivo.
            $table->index(['clinic_id', 'status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['clinic_id', 'status', 'completed_at']);
        });
    }
};
