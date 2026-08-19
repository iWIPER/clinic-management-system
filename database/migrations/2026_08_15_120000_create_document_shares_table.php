<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            // Polimórfico: Document ou AnamnesisInstance hoje; permite
            // estender a outras categorias de PDF clínico sem nova tabela.
            $table->string('shareable_type');
            $table->unsignedBigInteger('shareable_id');
            $table->string('token', 64)->unique();
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('friendly_filename');
            $table->string('storage_path');
            // Senha em texto plano nunca é gravada — 'encrypted' cast
            // (Crypt::encryptString, ligado à APP_KEY) permite reexibir ao
            // destinatário verificado sem guardar texto puro no banco.
            $table->text('password_encrypted');
            $table->timestamp('password_revealed_at')->nullable();
            $table->unsignedTinyInteger('identity_attempts')->default(0);
            $table->timestamp('identity_locked_until')->nullable();
            $table->string('status')->default('pending'); // pending|viewed|expired|revoked
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shareable_type', 'shareable_id']);
            $table->index(['clinic_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_shares');
    }
};
