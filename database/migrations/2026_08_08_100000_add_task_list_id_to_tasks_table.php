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
        Schema::table('tasks', function (Blueprint $table) {
            // Escopo personalizado (ex.: "Financeiro", "Recepção") — nulo
            // significa que a tarefa cai no cálculo padrão de mine/team via
            // assigned_to/created_by, como sempre foi. Uma tarefa vive em
            // exatamente um escopo por vez: quando isto é preenchido, ela sai
            // dos buckets mine/team (ver TaskListingService).
            $table->foreignId('task_list_id')->nullable()->after('patient_id')->constrained('task_lists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_list_id');
        });
    }
};
