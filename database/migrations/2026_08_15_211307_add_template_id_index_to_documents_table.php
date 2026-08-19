<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase B6.1.3 — comprovado com EXPLAIN ANALYZE no dataset de benchmark
 * (Postgres 16, ~38,5k documents / 669 document_templates). documents não
 * tinha índice em template_id.
 *
 * DocumentTemplateController::index() (ClinicSettings/Documentos — lista
 * todos os modelos da clínica) e DocumentController::category() (mesma
 * lista filtrada por categoria) usam withCount('documents'), que o Laravel
 * traduz numa subquery correlata "(select count(*) from documents where
 * document_templates.id = documents.template_id)" — sem índice, isso é um
 * Seq Scan na tabela documents inteira PARA CADA template (9 templates → 9
 * varreduras de 38,5k linhas).
 *
 * Medido: 30,1ms Seq Scan repetido (loops=9) → 1,97ms Index Only Scan
 * (-93,5%). Reproduzido em outra clínica grande (1,3ms), numa clínica
 * pequena (1,6ms) e na variante filtrada por categoria (0,35ms).
 *
 * NÃO alterado (já suficientemente rápido, tabelas pequenas — registrado,
 * não otimizado):
 * - documents(patient_id, created_at): já criado na B6.1.1, continua sendo
 *   usado (Index Scan Backward / Index Only Scan) tanto pela listagem
 *   paginada quanto pelo count() da paginação da aba Documentos. Nenhuma
 *   duplicação criada aqui.
 * - document_categories (510 linhas) e document_templates (669 linhas) em
 *   si: Seq Scan já custa <1,5ms — volume não justifica índice.
 * - document_signatures, document_shares, document_share_logs: 0 linhas no
 *   dataset de benchmark (não fazem parte dos volumes gerados na B6.0) —
 *   sem evidência possível; não fabriquei dado só pra medir. Os índices
 *   que já existem nessas tabelas (document_id+signer_role;
 *   clinic_id+patient_id; document_share_id+action) parecem adequados ao
 *   formato das queries reais, mas isso não foi comprovado por medição.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->index('template_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['template_id']);
        });
    }
};
