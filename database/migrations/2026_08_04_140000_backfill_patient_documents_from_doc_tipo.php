<?php

use App\Models\Patient;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * doc_tipo/doc_numero deixaram de ser lidos/gravados pelo formulário —
     * cpf/rg/passaporte são as novas colunas (permitem os dois primeiros
     * preenchidos ao mesmo tempo). Copia o dado já existente para não
     * "sumir" CPF de pacientes já cadastrados nas telas que passam a ler
     * das colunas novas (listagem, ficha, visão geral).
     */
    public function up(): void
    {
        Patient::query()
            ->whereNotNull('doc_tipo')
            ->whereNotNull('doc_numero')
            ->where('doc_numero', '!=', '')
            ->get(['id', 'doc_tipo', 'doc_numero'])
            ->each(function (Patient $patient) {
                match ($patient->doc_tipo) {
                    'cpf' => $patient->forceFill(['cpf' => $patient->doc_numero])->save(),
                    'rg' => $patient->forceFill(['rg' => $patient->doc_numero])->save(),
                    'passaporte' => $patient->forceFill([
                        'passaporte' => $patient->doc_numero,
                        'is_estrangeiro' => true,
                    ])->save(),
                    default => null, // 'outro' ou valor inesperado: sem coluna nova equivalente, fica só no legado.
                };
            });
    }

    /**
     * Migration de dados, não de schema — reverter não é o caminho normal
     * de uso (ver mesmo raciocínio na migration de backfill de Observações).
     */
    public function down(): void
    {
        //
    }
};
