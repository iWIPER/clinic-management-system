<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `patients.convenio` era texto livre. Resolve cada valor distinto (por
     * clínica) para uma linha em `convenios` (criando-a se ainda não existir)
     * e aponta `convenio_id` para ela. Depois disso a coluna de texto livre
     * é removida — convenio_id passa a ser a única fonte de verdade.
     */
    public function up(): void
    {
        $now = now();

        $rows = DB::table('patients')
            ->select('id', 'clinic_id', 'convenio')
            ->whereNotNull('convenio')
            ->where('convenio', '!=', '')
            ->orderBy('id')
            ->get();

        $cache = [];

        foreach ($rows as $row) {
            $nome = trim($row->convenio);
            $key  = $row->clinic_id . '|' . mb_strtolower($nome);

            if (! isset($cache[$key])) {
                $convenioId = DB::table('convenios')
                    ->where('clinic_id', $row->clinic_id)
                    ->whereRaw('LOWER(nome) = ?', [mb_strtolower($nome)])
                    ->value('id');

                if (! $convenioId) {
                    $convenioId = DB::table('convenios')->insertGetId([
                        'clinic_id'  => $row->clinic_id,
                        'nome'       => $nome,
                        'ativo'      => true,
                        'ordem'      => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $cache[$key] = $convenioId;
            }

            DB::table('patients')->where('id', $row->id)->update(['convenio_id' => $cache[$key]]);
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('convenio');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('convenio')->nullable()->after('origem');
        });

        // Valores originais em texto livre não são recuperáveis (irreversível com segurança).
    }
};
