<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->unsignedSmallInteger('inatividade_meses')->nullable()->after('duracao_padrao');
        });

        // Seed defaults for catalog treatments already in the database.
        // Groups (tipo='grupo') are intentionally omitted — they cannot be performed directly.
        $defaults = [
            'avaliacao-consulta-inicial'    => 12,
            'avaliacao-consulta-retorno'    => 12,
            'avaliacao-ortodontica'         => 12,
            'avaliacao-implantodontia'      => 18,
            'avaliacao-estetica'            => 12,

            'dentistica-profilaxia-basica'       => 8,
            'dentistica-profilaxia-completa'     => 8,
            'dentistica-aplicacao-fluor'         => 8,
            'dentistica-selante'                 => 24,
            'dentistica-resina-1-face'           => 24,
            'dentistica-resina-2-faces'          => 24,
            'dentistica-resina-3-faces'          => 24,
            'dentistica-resina-4-faces'          => 24,
            'dentistica-resina-estetica-anterior' => 24,

            'endo-canal-incisivo'     => 9,
            'endo-canal-premolar'     => 9,
            'endo-canal-molar'        => 9,
            'endo-canal-retratamento' => 18,

            'cirurgia-extracao-simples'  => 18,
            'cirurgia-extracao-complexa' => 18,
            'cirurgia-siso-incluso'      => 18,
            'cirurgia-siso-semi-incluso' => 18,
            'cirurgia-frenectomia'       => 18,

            'perio-raspagem-supra'         => 6,
            'perio-raspagem-sub'           => 6,
            'perio-gengivoplastia'         => 12,
            'perio-tratamento-periodontal' => 6,

            'implante-unitario'      => 12,
            'implante-coroa'         => 12,
            'implante-multiplo'      => 12,
            'implante-enxerto-osseo' => 12,

            'protese-coroa-metaloceramica' => 12,
            'protese-coroa-porcelana'      => 12,
            'protese-faceta-resina'        => 18,
            'protese-faceta-porcelana'     => 18,
            'protese-parcial'              => 12,
            'protese-total'                => 12,

            'orto-documentacao'        => 12,
            'orto-instalacao-metalico' => 2,
            'orto-instalacao-estetico' => 2,
            'orto-manutencao-mensal'   => 2,
            'orto-remocao-aparelho'    => 12,

            'radio-periapical' => 12,
            'radio-panoramica' => 12,
            'radio-tomografia' => 24,

            'estetica-clareamento-caseiro'     => 12,
            'estetica-clareamento-consultorio' => 12,
            'estetica-clareamento-combinado'   => 12,
        ];

        foreach ($defaults as $slug => $months) {
            DB::table('treatments')
                ->where('catalog_slug', $slug)
                ->update(['inatividade_meses' => $months]);
        }
    }

    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropColumn('inatividade_meses');
        });
    }
};
