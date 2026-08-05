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
        Schema::table('clinical_evolutions', function (Blueprint $table) {
            // Marcado no modal "Adicionar evolução" (toggle "Exigir assinatura").
            // O status exibido (Assinatura pendente / Assinado / sem etiqueta) é
            // derivado deste campo + a existência de uma assinatura — a tabela
            // de assinaturas em si (clinical_evolution_signatures) é adicionada
            // numa etapa separada, seguindo o mesmo padrão de DocumentSignature.
            $table->boolean('signature_required')->default(false)->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinical_evolutions', function (Blueprint $table) {
            $table->dropColumn('signature_required');
        });
    }
};
