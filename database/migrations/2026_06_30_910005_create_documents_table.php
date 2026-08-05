<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('document_templates')->restrictOnDelete();
            $table->foreignId('template_version_id')->constrained('document_template_versions')->restrictOnDelete();
            $table->string('template_name');
            $table->foreignId('professional_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->longText('rendered_html');
            $table->string('pdf_path')->nullable();
            $table->string('validation_token')->nullable()->unique();
            $table->string('signature_token')->nullable()->unique();
            $table->timestamp('signature_token_expires_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('document_code')->unique();
            $table->string('content_hash', 64)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
