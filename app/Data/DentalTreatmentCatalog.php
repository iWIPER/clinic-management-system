<?php

namespace App\Data;

class DentalTreatmentCatalog
{
    /**
     * Categorias e cores usadas para organizar manualmente o catálogo de
     * procedimentos (dropdown/chips em Treatments/Index.vue). A lista de
     * procedimentos em si vem do import da WilDental — ver
     * App\Services\WildentalCatalogService.
     */
    public static function categories(): array
    {
        return [
            'Avaliação' => ['cor' => '#3b82f6', 'ordem' => 1],
            'Dentística' => ['cor' => '#10b981', 'ordem' => 2],
            'Endodontia' => ['cor' => '#8b5cf6', 'ordem' => 3],
            'Cirurgia' => ['cor' => '#ef4444', 'ordem' => 4],
            'Periodontia' => ['cor' => '#f59e0b', 'ordem' => 5],
            'Implantodontia' => ['cor' => '#06b6d4', 'ordem' => 6],
            'Prótese' => ['cor' => '#6366f1', 'ordem' => 7],
            'Ortodontia' => ['cor' => '#ec4899', 'ordem' => 8],
            'Radiologia' => ['cor' => '#64748b', 'ordem' => 9],
            'Estética' => ['cor' => '#f472b6', 'ordem' => 10],
        ];
    }
}
