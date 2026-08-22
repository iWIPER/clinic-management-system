<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de exportações de dados de titular (LGPD) geradas pelo Backoffice
 * — nunca dado de clínica (sem BelongsToClinic/ClinicScope: é uma ferramenta
 * administrativa cross-tenant por natureza, ver EnsureCurrentClinic/
 * SystemAdmin). clinic_id é só metadado (a clínica do paciente exportado,
 * quando subject_type=patient), não um filtro de posse desta tabela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_subject_exports', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type'); // user|patient
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('generation_status')->default('processing'); // processing|ready|failed
            $table->text('generation_failed_reason')->nullable();
            $table->string('storage_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_subject_exports');
    }
};
