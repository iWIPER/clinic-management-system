<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passo 1/2 da migração consultation_id -> appointment_id (Appointment vira
 * a fonte de verdade da consulta; Consultation passa a ser só o registro
 * operacional interno de check-in, não mais obrigatório para existir uma
 * consulta no histórico). Nullable de propósito nos dois lados — não usa
 * ->change() (exigiria doctrine/dbal, não instalado) e mantém o mesmo nível
 * de "obrigatoriedade" que clinical_records.appointment_id já tem (também
 * nullable, já existia antes desta migration). A coluna consultation_id
 * antiga só é removida na migration seguinte, depois de validado o backfill
 * (nunca no mesmo passo que o dado poderia estar incompleto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_executions', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable()->after('consultation_id')
                ->constrained()->nullOnDelete();
        });

        $this->backfill('procedure_executions');
        $this->backfill('clinical_records');
    }

    /**
     * consultation_id -> consultations.appointment_id, via query builder
     * puro (sem SQL cru dialect-specific: local é MySQL, produção é
     * Postgres). Dataset pequeno (confirmado por auditoria: 5
     * procedure_executions + 7 clinical_records localmente) — loop simples,
     * sem necessidade de chunk.
     */
    private function backfill(string $table): void
    {
        DB::table($table)
            ->whereNotNull('consultation_id')
            ->whereNull('appointment_id')
            ->get(['id', 'consultation_id'])
            ->each(function ($row) use ($table) {
                $appointmentId = DB::table('consultations')->where('id', $row->consultation_id)->value('appointment_id');

                if ($appointmentId) {
                    DB::table($table)->where('id', $row->id)->update(['appointment_id' => $appointmentId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('procedure_executions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appointment_id');
        });

        DB::table('clinical_records')->update(['appointment_id' => null]);
    }
};
