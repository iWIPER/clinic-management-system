<?php

use App\Models\Patient;
use App\Models\PatientNote;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * patients.observacoes deixou de ser lido/gravado pela aplicação — o
     * módulo Observações passa a ser a única fonte de verdade. Esta migration
     * converte o conteúdo já existente em uma Observação real ("Observação
     * inicial"), para não deixar dado histórico inacessível na UI.
     *
     * Pacientes sem created_by_id/updated_by_id (registros antigos) são
     * pulados e reportados no log — sem autor rastreável não há como criar
     * uma observação válida (author_id é obrigatório), e inventar um autor
     * seria pior do que deixar o dado como está no banco.
     */
    public function up(): void
    {
        $skipped = [];

        Patient::query()
            ->whereNotNull('observacoes')
            ->where('observacoes', '!=', '')
            ->get(['id', 'clinic_id', 'observacoes', 'created_by_id', 'updated_by_id'])
            ->each(function (Patient $patient) use (&$skipped) {
                $authorId = $patient->created_by_id ?? $patient->updated_by_id;

                if (! $authorId) {
                    $skipped[] = $patient->id;
                    return;
                }

                PatientNote::create([
                    'clinic_id' => $patient->clinic_id,
                    'patient_id' => $patient->id,
                    'author_id' => $authorId,
                    'title' => 'Observação inicial',
                    'description' => $patient->observacoes,
                ]);
            });

        if ($skipped) {
            Log::warning('[backfill_patient_observacoes] Pacientes sem autor rastreável, não migrados', [
                'patient_ids' => $skipped,
            ]);
        }
    }

    /**
     * Migration de dados, não de schema — reverter não é o caminho normal
     * de uso (as Observações criadas passam a ser editáveis/apagáveis pelo
     * próprio módulo, como qualquer outra). Sem down() automático para não
     * apagar observações que o usuário já tenha editado desde a migração.
     */
    public function down(): void
    {
        //
    }
};
