<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_share_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_share_id')->constrained()->cascadeOnDelete();
            // created|sent_email|identity_failed|identity_locked|password_revealed|
            // document_viewed|password_sent_email|password_sent_whatsapp|password_sent_sms|revoked
            $table->string('action');
            // Ator autenticado do lado da clínica (quem iniciou); nulo nas
            // ações feitas pelo paciente/destinatário no fluxo público.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            // Nunca contém a senha em texto puro — ver DocumentShareService.
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['document_share_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_share_logs');
    }
};
