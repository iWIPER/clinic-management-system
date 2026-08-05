<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // "planned" (antigo workflow_status do odontograma) mapeia pra "futuro" —
    // o sistema não tem mais status "planejado" (ver
    // 2026_07_31_000001_migrate_planejado_cancelado_status_to_futuro).
    private const WORKFLOW_TO_STATUS = [
        'planned'     => 'futuro',
        'in_progress' => 'em_andamento',
        'completed'   => 'concluido',
        'future'      => 'futuro',
    ];

    /**
     * `patient_odontograms.teeth_data[dente].procedures[]` era, até aqui, o
     * único lugar onde "tratamentos" do paciente existiam. O módulo novo
     * (`patient_treatments`) passa a ser a única fonte de verdade, então
     * cada procedimento embutido vira uma linha aqui (melhor esforço — sem
     * profissional/convênio estruturados, isso vira texto em `notes`) e o
     * array `procedures` é zerado do JSON do odontograma.
     */
    public function up(): void
    {
        $now = now();
        $dailyCounters = [];

        $odontograms = DB::table('patient_odontograms')->select('id', 'clinic_id', 'patient_id', 'teeth_data')->get();

        foreach ($odontograms as $odontogram) {
            $teethData = json_decode($odontogram->teeth_data ?? '[]', true) ?: [];
            $changed = false;

            foreach ($teethData as $fdi => $tooth) {
                $procedures = $tooth['procedures'] ?? [];

                if (! empty($procedures)) {
                    foreach ($procedures as $proc) {
                        $this->createPatientTreatment(
                            $odontogram->clinic_id,
                            $odontogram->patient_id,
                            (string) $fdi,
                            $proc,
                            $dailyCounters,
                            $now
                        );
                    }
                }

                if (array_key_exists('procedures', $tooth)) {
                    $teethData[$fdi]['procedures'] = [];
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table('patient_odontograms')->where('id', $odontogram->id)->update([
                    'teeth_data' => json_encode($teethData),
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function createPatientTreatment(int $clinicId, int $patientId, string $tooth, array $proc, array &$dailyCounters, Carbon $now): void
    {
        $name = trim((string) ($proc['name'] ?? '')) ?: 'Procedimento sem nome (migrado)';
        $status = self::WORKFLOW_TO_STATUS[$proc['workflow_status'] ?? ''] ?? 'futuro';

        $treatmentDate = $this->parseDateOrNull($proc['started_at'] ?? null) ?? $now->copy();
        $completedAt   = $this->parseDateOrNull($proc['completed_at'] ?? null);

        $professionalText = trim((string) ($proc['professional'] ?? ''));
        $insuranceText    = trim((string) ($proc['insurance'] ?? ''));

        $convenioId = null;
        if ($insuranceText !== '') {
            $convenioId = DB::table('convenios')
                ->where('clinic_id', $clinicId)
                ->whereRaw('LOWER(nome) = ?', [mb_strtolower($insuranceText)])
                ->value('id');
        }

        $notesParts = [];
        if ($professionalText !== '' ) {
            $notesParts[] = "Profissional (registro migrado do odontograma): {$professionalText}";
        }
        if ($insuranceText !== '' && ! $convenioId) {
            $notesParts[] = "Convênio (registro migrado do odontograma): {$insuranceText}";
        }
        if (! empty($proc['notes'])) {
            $notesParts[] = trim((string) $proc['notes']);
        }

        DB::table('patient_treatments')->insert([
            'clinic_id'        => $clinicId,
            'patient_id'       => $patientId,
            'treatment_id'     => null,
            'procedure_name'   => $name,
            'professional_id'  => null,
            'convenio_id'      => $convenioId,
            'budget_code'      => $this->nextBudgetCode($clinicId, $treatmentDate, $dailyCounters),
            'tooth'            => $tooth,
            'faces'            => null,
            'value_charged'    => is_numeric($proc['price'] ?? null) ? (float) $proc['price'] : 0,
            'cost'             => 0,
            'status'           => $status,
            'treatment_date'   => $treatmentDate->toDateString(),
            'completed_at'     => $completedAt,
            'notes'            => $notesParts ? implode("\n", $notesParts) : null,
            'created_by_id'    => null,
            'updated_by_id'    => null,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
    }

    private function parseDateOrNull(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function nextBudgetCode(int $clinicId, Carbon $date, array &$dailyCounters): string
    {
        $key = $clinicId . '|' . $date->format('Ymd');

        if (! isset($dailyCounters[$key])) {
            $dailyCounters[$key] = DB::table('patient_treatments')
                ->where('clinic_id', $clinicId)
                ->where('budget_code', 'like', 'TRT-' . $date->format('ymd') . '-%')
                ->count();
        }

        $dailyCounters[$key]++;

        return sprintf('TRT-%s-%04d', $date->format('ymd'), $dailyCounters[$key]);
    }

    public function down(): void
    {
        // Irreversível com segurança: não há como recompor o JSON original
        // do odontograma a partir das linhas relacionais.
    }
};
