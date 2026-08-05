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
        Schema::create('clinical_evolution_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evolution_id')->unique()->constrained('clinical_evolutions')->cascadeOnDelete();
            $table->string('patient_name');
            $table->string('patient_cpf', 20)->nullable();
            $table->string('patient_email')->nullable();
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_evolution_signatures');
    }
};
