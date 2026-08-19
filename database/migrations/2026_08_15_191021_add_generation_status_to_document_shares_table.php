<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase B5: geração do PDF protegido + upload S3 + envio do e-mail deixaram
 * de rodar dentro da requisição HTTP (~5s+ de PDF/criptografia, medido
 * localmente, mais o round-trip do S3) — agora rodam num job. Esta coluna
 * é o que a UI usa para mostrar "Processando…" / "Falhou" enquanto isso
 * acontece. Não reaproveita 'status' (que já significa o ciclo de vida do
 * link do lado do destinatário: pending/viewed/expired/revoked).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_shares', function (Blueprint $table) {
            $table->string('generation_status')->default('processing')->after('status'); // processing|sent|failed
            $table->string('generation_failed_reason')->nullable()->after('generation_status');
        });
    }

    public function down(): void
    {
        Schema::table('document_shares', function (Blueprint $table) {
            $table->dropColumn(['generation_status', 'generation_failed_reason']);
        });
    }
};
