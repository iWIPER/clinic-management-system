<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda só a INTENÇÃO de enviar confirmação — não existe integração de
     * envio (WhatsApp/SMS/e-mail) ainda. Nunca deve ser interpretada como
     * "mensagem enviada"; é só a preferência marcada no momento do agendamento.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('confirmation_requested')->nullable()->default(false)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('confirmation_requested');
        });
    }
};
