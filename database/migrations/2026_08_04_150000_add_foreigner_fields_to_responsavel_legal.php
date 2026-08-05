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
        Schema::table('patients', function (Blueprint $table) {
            // Mesma lógica de is_estrangeiro/passaporte do paciente, aplicada
            // ao responsável legal.
            $table->boolean('responsavel_legal_estrangeiro')->default(false)->after('responsavel_legal_rg');
            $table->string('responsavel_legal_passaporte')->nullable()->after('responsavel_legal_estrangeiro');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['responsavel_legal_estrangeiro', 'responsavel_legal_passaporte']);
        });
    }
};
