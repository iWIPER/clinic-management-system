<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sem FK constraint de propósito, mesmo padrão já usado em
     * anamnesis_template_id: aponta para anamnesis_instances, tabela de um
     * módulo ainda não commitado (ver comentário equivalente na migration de
     * patient_invites). Preenchida por
     * PatientInviteService::findOrCreateAnamnesisInstance() na primeira vez
     * que o convite alcança a etapa de Anamnese (Fase 4) — permite retomada
     * sem depender de nenhuma coluna nova no lado do hub de Anamnese.
     */
    public function up(): void
    {
        Schema::table('patient_invites', function (Blueprint $table) {
            $table->unsignedBigInteger('anamnesis_instance_id')->nullable()->after('anamnesis_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('patient_invites', function (Blueprint $table) {
            $table->dropColumn('anamnesis_instance_id');
        });
    }
};
