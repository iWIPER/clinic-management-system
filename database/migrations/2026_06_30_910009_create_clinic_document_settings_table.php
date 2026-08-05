<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_document_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('default_signature_expiration_hours')->default(72);
            $table->boolean('footer_show_qrcode')->default(true);
            $table->boolean('footer_show_hash')->default(true);
            $table->text('footer_custom_text')->nullable();
            $table->boolean('header_show_patient_photo')->default(false);
            $table->boolean('require_professional_signature_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_document_settings');
    }
};
