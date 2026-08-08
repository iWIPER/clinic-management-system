<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_labels', function (Blueprint $table) {
            $table->id();
            // clinic_id nulo = etiqueta global (mesmo padrão de patient_tags),
            // disponível em toda clínica sem precisar duplicar o registro.
            $table->foreignId('clinic_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('#64748b');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_labels');
    }
};
