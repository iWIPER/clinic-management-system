<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_treatment_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_treatment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->json('metadata')->nullable();
            $table->dateTime('created_at');

            $table->index(['patient_treatment_id', 'created_at'], 'pt_audit_logs_pt_id_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_treatment_audit_logs');
    }
};
