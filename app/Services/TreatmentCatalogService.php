<?php

namespace App\Services;

use App\Data\DentalTreatmentCatalog;
use App\Models\Clinic;
use App\Models\Treatment;
use App\Models\TreatmentAuditLog;

class TreatmentCatalogService
{
    public function seedForClinic(Clinic $clinic, ?int $userId = null): int
    {
        $categories = DentalTreatmentCatalog::categories();
        $items = DentalTreatmentCatalog::items();
        $created = 0;
        $slugMap = [];

        foreach ($items as $item) {
            if (! empty($item['parent'])) {
                continue;
            }

            $catMeta = $categories[$item['categoria']] ?? ['cor' => '#10b981', 'ordem' => 0];

            $treatment = Treatment::withoutGlobalScopes()->firstOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'catalog_slug' => $item['slug'],
                ],
                [
                    'nome' => $item['nome'],
                    'categoria' => $item['categoria'],
                    'tipo' => $item['tipo'],
                    'especialidade' => $item['especialidade'],
                    'duracao_padrao' => $item['duracao'],
                    'preco_base' => $item['preco'],
                    'descricao' => $item['descricao'],
                    'cor' => $catMeta['cor'],
                    'ordem' => $item['ordem'],
                    'ativo' => true,
                ]
            );

            $slugMap[$item['slug']] = $treatment->id;

            if ($treatment->wasRecentlyCreated) {
                TreatmentAuditLog::create([
                    'clinic_id' => $clinic->id,
                    'treatment_id' => $treatment->id,
                    'user_id' => $userId,
                    'action' => 'created',
                    'metadata' => ['source' => 'dental_catalog_seed', 'nome' => $treatment->nome],
                    'created_at' => now(),
                ]);
                $created++;
            }
        }

        foreach ($items as $item) {
            if (empty($item['parent'])) {
                continue;
            }

            $catMeta = $categories[$item['categoria']] ?? ['cor' => '#10b981', 'ordem' => 0];
            $parentId = $slugMap[$item['parent']] ?? Treatment::withoutGlobalScopes()
                ->where('clinic_id', $clinic->id)
                ->where('catalog_slug', $item['parent'])
                ->value('id');

            $treatment = Treatment::withoutGlobalScopes()->firstOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'catalog_slug' => $item['slug'],
                ],
                [
                    'parent_id' => $parentId,
                    'nome' => $item['nome'],
                    'categoria' => $item['categoria'],
                    'tipo' => $item['tipo'],
                    'especialidade' => $item['especialidade'],
                    'duracao_padrao' => $item['duracao'],
                    'preco_base' => $item['preco'],
                    'descricao' => $item['descricao'],
                    'cor' => $catMeta['cor'],
                    'ordem' => $item['ordem'],
                    'ativo' => true,
                ]
            );

            if ($treatment->wasRecentlyCreated) {
                TreatmentAuditLog::create([
                    'clinic_id' => $clinic->id,
                    'treatment_id' => $treatment->id,
                    'user_id' => $userId,
                    'action' => 'created',
                    'metadata' => ['source' => 'dental_catalog_seed', 'nome' => $treatment->nome],
                    'created_at' => now(),
                ]);
                $created++;
            }
        }

        return $created;
    }

    public function ensureCatalogForCurrentClinic(?int $userId = null): int
    {
        $clinicId = session('current_clinic_id');
        if (! $clinicId) {
            return 0;
        }

        $clinic = Clinic::find($clinicId);
        if (! $clinic) {
            return 0;
        }

        return $this->seedForClinic($clinic, $userId);
    }
}