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
            // particular | convenio | outro — convenio_id (já existente)
            // continua sendo o select de qual convênio.
            $table->string('tipo_atendimento', 20)->default('particular')->after('convenio_id');
            $table->string('convenio_numero_carteirinha')->nullable()->after('tipo_atendimento');
            $table->string('convenio_titular')->nullable()->after('convenio_numero_carteirinha');
            $table->string('convenio_titular_cpf')->nullable()->after('convenio_titular');
            $table->string('convenio_titular_parentesco', 30)->nullable()->after('convenio_titular_cpf');
            $table->string('tipo_atendimento_outro_descricao')->nullable()->after('convenio_titular_parentesco');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_atendimento',
                'convenio_numero_carteirinha',
                'convenio_titular',
                'convenio_titular_cpf',
                'convenio_titular_parentesco',
                'tipo_atendimento_outro_descricao',
            ]);
        });
    }
};
