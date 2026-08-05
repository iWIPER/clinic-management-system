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
            $table->boolean('possui_responsavel_legal')->default(false)->after('canal_lembrete');
            $table->string('responsavel_legal_nome')->nullable()->after('possui_responsavel_legal');
            $table->string('responsavel_legal_cpf')->nullable()->after('responsavel_legal_nome');
            $table->string('responsavel_legal_rg')->nullable()->after('responsavel_legal_cpf');
            $table->string('responsavel_legal_telefone')->nullable()->after('responsavel_legal_rg');
            $table->string('responsavel_legal_parentesco', 30)->nullable()->after('responsavel_legal_telefone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'possui_responsavel_legal',
                'responsavel_legal_nome',
                'responsavel_legal_cpf',
                'responsavel_legal_rg',
                'responsavel_legal_telefone',
                'responsavel_legal_parentesco',
            ]);
        });
    }
};
