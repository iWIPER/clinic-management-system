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
            // Substituem doc_tipo/doc_numero (mantidos, não usados mais pelo
            // formulário) — permitem CPF e RG preenchidos ao mesmo tempo.
            $table->string('cpf')->nullable()->after('doc_numero');
            $table->string('rg')->nullable()->after('cpf');
            $table->string('passaporte')->nullable()->after('rg');
            $table->boolean('is_estrangeiro')->default(false)->after('passaporte');

            // Mesma forma da antiga coluna profissao (ver down() de
            // 2025_01_01_000015_drop_profissao_estado_civil_from_patients_table).
            $table->string('profissao')->nullable()->after('is_estrangeiro');

            // Só armazena o canal preferido — nenhuma lógica de envio associada.
            $table->string('canal_lembrete', 20)->default('nao_enviar')->after('profissao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['cpf', 'rg', 'passaporte', 'is_estrangeiro', 'profissao', 'canal_lembrete']);
        });
    }
};
