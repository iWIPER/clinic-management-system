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
        // VARCHAR simples (sem enum de banco) para não travar futuros valores
        // (importacao, api, sistema) numa migration nova. NOT NULL + DEFAULT
        // 'manual' faz o MySQL preencher automaticamente todas as linhas já
        // existentes ao adicionar a coluna — sem precisar de um UPDATE extra.
        Schema::table('patient_notes', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_notes', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
