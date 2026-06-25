<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_anamneses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->text('queixa_principal')->nullable();
            $table->text('historico_medico')->nullable();
            $table->text('alergias')->nullable();
            $table->text('medicamentos_em_uso')->nullable();
            $table->text('doencas_sistemicas')->nullable();
            $table->text('historico_familiar')->nullable();
            $table->boolean('gestante')->default(false);
            $table->boolean('hipertensao')->default(false);
            $table->boolean('diabetes')->default(false);
            $table->boolean('cardiopatia')->default(false);
            $table->boolean('hemorragia')->default(false);
            $table->boolean('fumo')->default(false);
            $table->boolean('alcool')->default(false);
            $table->text('habitos_outros')->nullable();
            $table->text('cirurgias_previas')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_anamneses');
    }
};