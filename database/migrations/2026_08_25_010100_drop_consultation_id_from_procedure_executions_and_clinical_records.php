<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passo 2/2 — só roda depois do backfill da migration anterior ter sido
 * validado (100% migrado, ver auditoria local). procedure_executions e
 * clinical_records passam a apontar exclusivamente pra appointment_id;
 * Consultation continua existindo como registro operacional interno
 * (check-in), só deixa de ser exigida pra Procedimento existir.
 */
return new class extends Migration
{
    public function up(): void
    {
        // hasColumn() torna seguro reexecutar após uma falha parcial (MySQL
        // não é transacional em DDL — um ALTER TABLE que já rodou não
        // desfaz sozinho se um passo seguinte falhar).
        if (Schema::hasColumn('procedure_executions', 'consultation_id')) {
            Schema::table('procedure_executions', function (Blueprint $table) {
                // Ordem importa e é DIFERENTE por banco: MySQL recusa dropar
                // o índice simples enquanto a FK ainda existir ("needed in a
                // foreign key constraint", erro 1553 — confirmado rodando
                // esta migration do zero contra um MySQL limpo), então a FK
                // sai primeiro aqui. SQLite, ao contrário, só quebra se o
                // índice sobreviver até a coluna ser dropada (reconstrói a
                // tabela e tenta recriar um índice apontando pra coluna que
                // já não existe) — dropForeign->dropIndex->dropColumn, nessa
                // ordem, satisfaz os dois.
                $table->dropForeign(['consultation_id']);

                if (Schema::hasIndex('procedure_executions', 'procedure_executions_consultation_id_index')) {
                    $table->dropIndex(['consultation_id']);
                }

                $table->dropColumn('consultation_id');
            });
        }

        if (Schema::hasColumn('clinical_records', 'consultation_id')) {
            Schema::table('clinical_records', function (Blueprint $table) {
                // MySQL exige remover a FK antes do índice único que a
                // sustenta — ordem inversa da primeira tentativa (erro 1553).
                $table->dropForeign(['consultation_id']);
                $table->dropUnique(['consultation_id']);
                $table->dropColumn('consultation_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('procedure_executions', function (Blueprint $table) {
            $table->foreignId('consultation_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('clinical_records', function (Blueprint $table) {
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->unique('consultation_id');
        });
    }
};
