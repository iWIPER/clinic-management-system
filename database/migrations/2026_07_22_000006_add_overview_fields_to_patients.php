<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // responsible_professional_id, created_by_id, updated_by_id e origem
        // já são criadas por 2026_07_21_235959_add_audit_and_origin_fields_to_patients_table.php
        // (extraídas para o módulo de Convites de Cadastro, que precisa delas
        // antes deste módulo existir) — aqui resta só a coluna legada
        // 'convenio' e o backfill histórico, ambos exclusivos desta feature.
        Schema::table('patients', function (Blueprint $table) {
            $table->string('convenio')->nullable()->after('origem');
        });

        $this->backfillResponsibleProfessional();
    }

    /**
     * One-time backfill: earliest completed clinical record's professional,
     * falling back to the earliest appointment's, falling back to the most
     * recent of either. After this migration the column is fully manual —
     * nothing else in the app ever overwrites it automatically.
     */
    private function backfillResponsibleProfessional(): void
    {
        DB::table('patients')->select('id')->orderBy('id')->chunkById(200, function ($patients) {
            foreach ($patients as $patient) {
                $professionalId = DB::table('clinical_records')
                    ->where('patient_id', $patient->id)
                    ->whereNotNull('finished_at')
                    ->orderBy('finished_at')
                    ->value('professional_id');

                if (! $professionalId) {
                    $professionalId = DB::table('appointments')
                        ->where('patient_id', $patient->id)
                        ->orderBy('start')
                        ->value('professional_id');
                }

                if (! $professionalId) {
                    $professionalId = DB::table('clinical_records')
                            ->where('patient_id', $patient->id)
                            ->orderByDesc('created_at')
                            ->value('professional_id')
                        ?? DB::table('appointments')
                            ->where('patient_id', $patient->id)
                            ->orderByDesc('created_at')
                            ->value('professional_id');
                }

                if ($professionalId) {
                    DB::table('patients')->where('id', $patient->id)
                        ->update(['responsible_professional_id' => $professionalId]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('convenio');
        });
    }
};
