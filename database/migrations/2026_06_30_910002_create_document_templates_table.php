<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('document_categories')->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            // FK adicionada na migration seguinte (depende de document_template_versions, que ainda não existe)
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->boolean('requires_patient_signature')->default(true);
            $table->boolean('requires_professional_signature')->default(false);
            $table->boolean('requires_responsible_signature')->default(false);
            $table->boolean('requires_witness_signature')->default(false);
            $table->unsignedInteger('signature_expiration_hours')->nullable()->default(72);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['clinic_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
