<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * O sistema passa a ter só 3 status de tratamento: futuro, em_andamento,
     * concluido. "planejado" (tratamento ainda não iniciado, sem data certa)
     * é semanticamente o mesmo conceito de "futuro" — vira futuro.
     * "cancelado" não tem mais representação própria; também vira futuro
     * (não há registros hoje, mas trata defensivamente caso existam no
     * banco de outro ambiente). Nenhuma linha é excluída, só o valor da
     * coluna status muda.
     */
    public function up(): void
    {
        DB::table('patient_treatments')
            ->whereIn('status', ['planejado', 'cancelado'])
            ->update(['status' => 'futuro']);
    }

    /**
     * Não reversível com segurança: depois do up(), não há como distinguir
     * quais linhas eram "futuro" de origem das que eram "planejado"/
     * "cancelado" antes da conversão.
     */
    public function down(): void
    {
        // Irreversível de propósito — ver comentário da classe.
    }
};
