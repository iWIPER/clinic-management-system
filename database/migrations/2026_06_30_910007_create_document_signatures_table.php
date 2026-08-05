<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('signer_role'); // patient|professional|responsible|witness
            $table->string('signer_name');
            $table->string('signer_cpf', 20)->nullable();
            $table->string('signer_email')->nullable();
            $table->foreignId('professional_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('professional_cro')->nullable();
            $table->string('signature_path');
            $table->string('signature_hash', 64);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->json('browser_info')->nullable();
            $table->json('geolocation')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();

            $table->index(['document_id', 'signer_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signatures');
    }
};
