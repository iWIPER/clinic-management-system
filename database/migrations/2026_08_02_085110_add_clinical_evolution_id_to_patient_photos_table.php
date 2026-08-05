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
        Schema::table('patient_photos', function (Blueprint $table) {
            // Reaproveita a tabela/pasta de fotos já existente (categoria
            // "Evoluções" no Drive) em vez de duplicar upload/gestão de arquivo
            // pra um novo tipo de anexo — ver GoogleDriveService::uploadPhoto().
            // nullOnDelete: apagar a evolução não some com a foto já enviada ao
            // Drive, só desvincula (nada no sistema hoje apaga evoluções).
            $table->foreignId('clinical_evolution_id')->nullable()->after('patient_id')
                ->constrained('clinical_evolutions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinical_evolution_id');
        });
    }
};
