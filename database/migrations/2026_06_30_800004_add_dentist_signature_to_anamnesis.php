<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Passo 1: adiciona colunas e o novo índice composto
        Schema::table('anamnesis_signatures', function (Blueprint $table) {
            $table->string('signer_type', 10)->default('patient')->after('instance_id');
            $table->foreignId('professional_id')->nullable()->after('signer_type')
                ->constrained('users')->nullOnDelete();
            $table->string('professional_cro', 30)->nullable()->after('professional_id');

            // Cria o novo unique composto ANTES de remover o antigo
            // (MySQL precisa de um índice suportando a FK durante o ALTER)
            $table->unique(['instance_id', 'signer_type'], 'anamnesis_signatures_instance_signer_unique');
        });

        // Passo 2: remove o antigo unique simples agora que o composto existe
        Schema::table('anamnesis_signatures', function (Blueprint $table) {
            $table->dropUnique('anamnesis_signatures_instance_id_unique');
        });
    }

    public function down(): void
    {
        // Passo 1: restaura o unique simples
        Schema::table('anamnesis_signatures', function (Blueprint $table) {
            $table->unique('instance_id', 'anamnesis_signatures_instance_id_unique');
        });

        // Passo 2: remove composto e colunas adicionadas
        Schema::table('anamnesis_signatures', function (Blueprint $table) {
            $table->dropUnique('anamnesis_signatures_instance_signer_unique');
            $table->dropForeign(['professional_id']);
            $table->dropColumn(['signer_type', 'professional_id', 'professional_cro']);
        });
    }
};
