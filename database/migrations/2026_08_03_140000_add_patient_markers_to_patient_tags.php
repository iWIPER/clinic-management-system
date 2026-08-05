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
        // Marcadores administrativos do paciente reaproveitam o mesmo
        // vocabulário de patient_tags (categorias de Observações) — mesma
        // forma de dado (clinic-scoped, nome, slug, cor), só que anexado ao
        // Patient em vez da PatientNote. is_patient_marker discrimina os dois
        // usos dentro da mesma tabela: false = categoria de observação
        // (comportamento atual, default), true = marcador administrativo.
        Schema::table('patient_tags', function (Blueprint $table) {
            $table->boolean('is_patient_marker')->default(false)->after('is_system');
        });

        Schema::create('patient_marker_assignments', function (Blueprint $table) {
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_tag_id')->constrained('patient_tags')->cascadeOnDelete();
            $table->primary(['patient_id', 'patient_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_marker_assignments');

        Schema::table('patient_tags', function (Blueprint $table) {
            $table->dropColumn('is_patient_marker');
        });
    }
};
