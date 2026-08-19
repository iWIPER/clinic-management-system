<?php

namespace App\Services;

use App\Models\Treatment;
use Illuminate\Support\Facades\Cache;

/**
 * Fase B7.1 — ponto central de leitura do catálogo de tratamentos de uma
 * clínica. Antes, PatientController, ConsultationController e
 * DocumentHubService consultavam Treatment direto, cada um do zero.
 *
 * Dois métodos porque são duas consultas REALMENTE diferentes (não a mesma
 * chave com dados diferentes):
 * - activeCatalog(): tratamentos ativos (scope active()) — usado pela ficha
 *   do paciente e, num subconjunto de colunas, pela aba Documentos.
 * - schedulableCatalog(): ativos E agendáveis (scope forScheduling(), um
 *   subconjunto mais estrito) — usado só pelo seletor de procedimento
 *   dentro de uma consulta.
 *
 * TTL curto (15min) como rede de segurança — a consistência real vem da
 * invalidação explícita em forgetClinic(), chamada por TreatmentController
 * em toda alteração administrativa (create/update/ativar/desativar/excluir).
 *
 * Cada valor cacheado é um array simples com só as colunas que os
 * consumidores de fato usam (não o Model Eloquent inteiro, não relações).
 */
class TreatmentCatalogService
{
    private const TTL_MINUTES = 15;

    public function activeCatalog(int $clinicId): array
    {
        return Cache::remember(
            $this->activeKey($clinicId),
            now()->addMinutes(self::TTL_MINUTES),
            fn () => Treatment::query()
                ->where('clinic_id', $clinicId)
                ->active()
                ->orderBy('nome')
                ->get(['id', 'nome', 'preco_base', 'custo_padrao'])
                ->map(fn (Treatment $t) => [
                    'id'           => $t->id,
                    'nome'         => $t->nome,
                    'preco_base'   => $t->preco_base,
                    'custo_padrao' => $t->custo_padrao,
                ])
                ->all()
        );
    }

    public function schedulableCatalog(int $clinicId): array
    {
        return Cache::remember(
            $this->schedulableKey($clinicId),
            now()->addMinutes(self::TTL_MINUTES),
            fn () => Treatment::query()
                ->where('clinic_id', $clinicId)
                ->forScheduling()
                ->orderBy('nome')
                ->get(['id', 'nome', 'duracao_padrao'])
                ->map(fn (Treatment $t) => [
                    'id'             => $t->id,
                    'nome'           => $t->nome,
                    'duracao_padrao' => $t->duracao_padrao,
                ])
                ->all()
        );
    }

    /**
     * Chamado por TreatmentController em toda alteração administrativa.
     * Nunca Cache::flush() — só as duas chaves desta clínica específica.
     */
    public function forgetClinic(int $clinicId): void
    {
        Cache::forget($this->activeKey($clinicId));
        Cache::forget($this->schedulableKey($clinicId));
    }

    private function activeKey(int $clinicId): string
    {
        return "treatments:active:{$clinicId}";
    }

    private function schedulableKey(int $clinicId): string
    {
        return "treatments:schedulable:{$clinicId}";
    }
}
