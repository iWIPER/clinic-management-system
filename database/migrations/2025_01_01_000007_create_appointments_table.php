<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('treatment_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('start');
            $table->dateTime('end');
            $table->string('status')->default('scheduled'); // scheduled, confirmed, cancelled, no_show, completed
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
