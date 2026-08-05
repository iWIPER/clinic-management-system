<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * O catálogo de procedimentos antigo (seedado por DentalTreatmentCatalog)
     * é substituído integralmente pela lista da WilDental. Os registros
     * antigos NÃO são excluídos (appointments/procedure_executions/budget_items
     * existentes referenciam esses ids e algumas dessas FKs fazem cascade
     * delete) — só desativados, preservando histórico. `catalog_slug` dos
     * itens novos sempre começa com "wildental-", então dá pra distinguir.
     */
    public function up(): void
    {
        DB::table('treatments')
            ->whereNotNull('catalog_slug')
            ->where('catalog_slug', 'not like', 'wildental-%')
            ->where('ativo', true)
            ->update([
                'ativo'        => false,
                'deactivated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('treatments')
            ->whereNotNull('catalog_slug')
            ->where('catalog_slug', 'not like', 'wildental-%')
            ->update([
                'ativo'          => true,
                'deactivated_at' => null,
            ]);
    }
};
