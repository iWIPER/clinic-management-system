<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fase "System Admin / Backoffice" — substitui o e-mail hardcoded em
        // SuperAdmin::EMAIL. Tabela própria (não um boolean em users) porque
        // o pedido exige histórico: quem concedeu, quando, quem removeu,
        // quando — um simples users.is_system_admin perderia o histórico de
        // remoções. Uma linha com revoked_at nulo = admin ativo; conceder de
        // novo depois de revogar cria uma NOVA linha (preserva o rastro
        // completo, nunca reescreve uma concessão/revogação passada).
        Schema::create('system_admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at');
            $table->foreignId('revoked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_admins');
    }
};
