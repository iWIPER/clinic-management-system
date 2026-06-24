<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('photo_id')->nullable();
            $table->string('event_type', 50);
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->nullOnDelete();
            $table->foreign('photo_id')->references('id')->on('patient_photos')->nullOnDelete();

            $table->index(['clinic_id', 'created_at']);
            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_activity_logs');
    }
};
