<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anamnesis_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instance_id')->unique()->constrained('anamnesis_instances')->cascadeOnDelete();
            $table->string('patient_name');
            $table->string('patient_cpf', 20)->nullable();
            $table->string('patient_email')->nullable();
            $table->string('google_id')->nullable();
            $table->string('google_name')->nullable();
            $table->string('google_email')->nullable();
            $table->string('signature_path');
            $table->string('signature_hash', 64);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->json('browser_info')->nullable();
            $table->json('geolocation')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();
        });

        // Adiciona novos status possíveis ao campo existente (apenas doc, sem change de coluna)
        // awaiting_signature, signed, cancelled são novos valores do enum InstanceStatus
    }

    public function down(): void
    {
        Schema::dropIfExists('anamnesis_signatures');
    }
};
