<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Treatment;
use App\Models\TreatmentAuditLog;
use Illuminate\Support\Str;

class WildentalCatalogService
{
    /**
     * Catálogo padrão (substitui integralmente o catálogo antigo hardcoded
     * em DentalTreatmentCatalog). Idempotente por clínica via
     * `catalog_slug = wildental-<slug do nome>`.
     */
    public function seedForClinic(Clinic $clinic, ?int $userId = null): int
    {
        $items = require base_path('database/seeders/data/wildental_procedimentos.php');
        $created = 0;
        $usedSlugs = [];

        foreach ($items as $item) {
            $baseSlug = 'wildental-' . Str::slug($item['nome']);
            // Alguns nomes distintos (ex.: "Coroa de Aço" vs "Coroa de Aço*")
            // geram o mesmo slug porque Str::slug() descarta o "*" — desambigua
            // com um sufixo numérico para não colidir e perder o item.
            $slug = $baseSlug;
            $suffix = 2;
            while (isset($usedSlugs[$slug])) {
                $slug = $baseSlug . '-' . $suffix;
                $suffix++;
            }
            $usedSlugs[$slug] = true;

            $treatment = Treatment::withoutGlobalScopes()->firstOrCreate(
                [
                    'clinic_id'    => $clinic->id,
                    'catalog_slug' => $slug,
                ],
                [
                    'nome'           => $item['nome'],
                    'categoria'      => null,
                    'tipo'           => 'procedimento',
                    'especialidade'  => null,
                    'duracao_padrao' => 30,
                    'preco_base'     => $item['valor'],
                    'custo_padrao'   => $item['valor'],
                    'descricao'      => null,
                    'cor'            => '#10b981',
                    'ordem'          => 0,
                    'ativo'          => true,
                ]
            );

            if ($treatment->wasRecentlyCreated) {
                TreatmentAuditLog::create([
                    'clinic_id'    => $clinic->id,
                    'treatment_id' => $treatment->id,
                    'user_id'      => $userId,
                    'action'       => 'created',
                    'metadata'     => ['source' => 'wildental_catalog_seed', 'nome' => $treatment->nome],
                    'created_at'   => now(),
                ]);
                $created++;
            }
        }

        return $created;
    }
}
