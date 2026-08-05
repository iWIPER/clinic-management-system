<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('action', 80);        // login, logout, invite_sent, etc.
            $table->string('description', 255)->nullable(); // texto legível

            $table->string('ip_address', 45)->nullable();  // IPv4 ou IPv6
            $table->text('user_agent')->nullable();

            $table->enum('device_type', ['desktop', 'notebook', 'tablet', 'mobile'])->default('desktop');
            $table->string('browser', 100)->nullable();
            $table->string('os', 100)->nullable();

            // Geolocalização opcional (pode ser preenchida assincronamente no futuro)
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();

            $table->json('metadata')->nullable(); // dados extras livres

            $table->timestamp('created_at')->useCurrent(); // logs são imutáveis

            // Índices para as queries mais comuns
            $table->index(['clinic_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
