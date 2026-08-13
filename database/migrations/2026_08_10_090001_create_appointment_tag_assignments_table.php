<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Etiquetas do agendamento reaproveitam o MESMO catálogo patient_tags
     * (marcadores) já usado pelo paciente — mesmo padrão de
     * patient_marker_assignments, só que anexado ao Appointment em vez do
     * Patient. Nenhuma tabela de vocabulário nova, só a associação.
     */
    public function up(): void
    {
        Schema::create('appointment_tag_assignments', function (Blueprint $table) {
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_tag_id')->constrained('patient_tags')->cascadeOnDelete();
            $table->primary(['appointment_id', 'patient_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_tag_assignments');
    }
};
