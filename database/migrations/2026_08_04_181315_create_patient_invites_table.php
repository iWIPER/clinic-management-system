<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patient_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            // Sem ->cascadeOnDelete() aqui de propósito: nem MySQL nem SQLite
            // permitem uma FK com ação CASCADE numa coluna da qual uma coluna
            // gerada (active_key, abaixo) depende. O cascade equivalente é
            // feito no model (Patient::boot(), mesmo padrão já usado em
            // Invite::boot()) — mesmo comportamento em qualquer driver.
            $table->foreignId('patient_id')->constrained();
            $table->string('kind'); // cadastro, atualizacao
            $table->string('token')->unique();
            $table->string('status'); // gerado, enviado, visualizado, em_preenchimento, aguardando_conclusao, concluido, expirado, cancelado

            // Regra de domínio (BRD PATIENT_INVITATIONS_BRD.md §5.2): no
            // máximo um convite não-terminal por (patient_id, kind). Nem
            // MySQL nem SQLite têm índice único parcial — o equivalente é uma
            // coluna gerada que só recebe valor quando a linha está "ativa";
            // linhas terminais caem em NULL, e um índice único nunca trata
            // dois NULLs como colisão entre si (comportamento idêntico nos
            // dois drivers — só a sintaxe interna do CASE muda, ver
            // self::activeKeyExpression()).
            $table->string('active_key', 255)->storedAs($this->activeKeyExpression());

            $table->string('channel'); // whatsapp, email, link_only
            $table->boolean('allow_insurance')->default(false);
            $table->boolean('allow_anamnesis')->default(false);
            // Sem FK constraint de propósito: a tabela anamnesis_templates
            // pertence a um módulo próprio (Anamnese), commitado separadamente.
            // O valor já é gravado desde a Fase 1 (seleção do modelo no
            // convite), mas a integridade referencial só é reforçada quando
            // esse módulo existir — reintroduzir a constraint então é uma
            // migration de uma linha, sem impacto nos dados já gravados.
            $table->unsignedBigInteger('anamnesis_template_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('current_step')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('anamnesis_completed_at')->nullable();
            $table->timestamp('not_responded_flagged_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('active_key', 'patient_invites_active_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_invites');
    }

    /**
     * Expressão do CASE que define active_key — mesma regra em qualquer
     * driver, só a sintaxe de concatenação de string muda (MySQL: CONCAT();
     * SQLite, usado pela suíte de testes via phpunit.xml: ||). Único ponto do
     * projeto que precisa conhecer essa diferença.
     */
    private function activeKeyExpression(): string
    {
        $concat = match (DB::connection()->getDriverName()) {
            'sqlite' => "(patient_id || ':' || kind)",
            'pgsql'  => '(patient_id::text || \':\' || kind)',
            default  => 'CONCAT(patient_id, \':\', kind)',
        };

        return "CASE WHEN status NOT IN ('concluido','expirado','cancelado') THEN {$concat} ELSE NULL END";
    }
};
