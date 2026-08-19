<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase B6.2.2 — comprovado com EXPLAIN ANALYZE no dataset de benchmark
 * (Postgres 16, ~184k transactions / ~11k budgets). Nenhuma das duas
 * tabelas tinha índice além da PK. Mapeei todo o módulo Finance antes de
 * medir: as 4 únicas queries de LEITURA reais em `transactions` no
 * codebase inteiro são as 4 de FinanceController::index() — todo o resto
 * (ConsultationController, PatientTreatmentController,
 * FinancingSettlementService, PatientPaymentService) só faz
 * Transaction::create().
 *
 * Medido (clínica com 18,4k transações — a maior do benchmark):
 *   budgets latest(5):                    42,8ms Seq Scan+Sort → 1,6ms Index Scan (-96%)
 *   transactions latest(10):              25,7ms Parallel Seq Scan+Sort → 0,72ms (-97%)
 *   sum(valor) receita+pago:              25,2ms Parallel Seq Scan → 3,8ms Bitmap Heap Scan (-85%)
 *   sum(valor) despesa+pago:              23,6ms Parallel Seq Scan → 3,7ms (-85%)
 * As duas agregações SUM não caem para sub-1ms como as outras porque somar
 * `valor` exige visitar as linhas no heap (não é possível Index Only Scan
 * com uma coluna fora do índice) — ainda assim, o ganho é real e grande.
 * Generalizado numa clínica pequena (124 transações) e num caso sem
 * nenhum resultado (clinic_id inexistente) — mesmo padrão, sem regressão.
 *
 * NÃO testado / sem query real (regra: não inventar):
 * - origem_type/origem_id: gravados em 4 lugares diferentes, mas NUNCA
 *   lidos de volta (`WHERE origem_type = ... AND origem_id = ...` não
 *   existe em nenhum lugar do código) — nenhuma hipótese para testar.
 * - budget_items: nenhuma query do módulo Finance os acessa
 *   (FinanceController::index() não faz eager load de 'items').
 *
 * SEM DADO NO BENCHMARK — registrado, não fabricado:
 * - patient_payments (0 linhas; já tem 2 índices compostos de fase
 *   anterior, não comprovados aqui por falta de volume).
 * - pricing_configs (0 linhas; por natureza é no máximo 1 linha por
 *   clínica, nunca precisaria de índice mesmo com dado real).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->index(['clinic_id', 'created_at']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['clinic_id', 'created_at']);
            $table->index(['clinic_id', 'tipo', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropIndex(['clinic_id', 'created_at']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['clinic_id', 'created_at']);
            $table->dropIndex(['clinic_id', 'tipo', 'status']);
        });
    }
};
