<?php

namespace App\Services\Anamnesis;

use App\Models\AnamnesisCategoryDefinition;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CategoryDefinitionService
{
    private const DEFAULTS = [
        'QUEIXA PRINCIPAL' => ['icon' => '💬', 'color' => '#0ea5e9', 'description' => 'Motivo da consulta e sintomas relatados.'],
        'GERAL' => ['icon' => '❤️', 'color' => '#ef4444', 'description' => 'Informações gerais de saúde.'],
        'DOENÇAS SISTÊMICAS' => ['icon' => '🩺', 'color' => '#8b5cf6', 'description' => 'Condições médicas e fatores de risco.'],
        'HISTÓRICO' => ['icon' => '📋', 'color' => '#6366f1', 'description' => 'Antecedentes e histórico de tratamentos.'],
        'HÁBITOS' => ['icon' => '🪥', 'color' => '#14b8a6', 'description' => 'Hábitos de higiene e estilo de vida.'],
        'ODONTOLÓGICO' => ['icon' => '🦷', 'color' => '#3b82f6', 'description' => 'Histórico e condições odontológicas.'],
        'EXAME CLÍNICO' => ['icon' => '🔍', 'color' => '#f59e0b', 'description' => 'Achados do exame clínico.'],
        'COVID' => ['icon' => '😷', 'color' => '#64748b', 'description' => 'Questionário COVID-19.'],
        'GESTAÇÃO' => ['icon' => '🤰', 'color' => '#ec4899', 'description' => 'Gestação e amamentação.'],
        'ESTÉTICA' => ['icon' => '✨', 'color' => '#a855f7', 'description' => 'Cuidados estéticos e HOF.'],
        'ORTODONTIA' => ['icon' => '📐', 'color' => '#06b6d4', 'description' => 'Avaliação ortodôntica.'],
        'PEDIATRIA' => ['icon' => '👶', 'color' => '#22c55e', 'description' => 'Informações pediátricas.'],
    ];

    public function listForClinic(?int $clinicId, bool $activeOnly = false): array
    {
        if (! Schema::hasTable('anamnesis_category_definitions')) {
            return [];
        }

        return AnamnesisCategoryDefinition::query()
            ->forClinic($clinicId)
            ->when($activeOnly, fn ($q) => $q->active())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (AnamnesisCategoryDefinition $c) => $this->serialize($c))
            ->all();
    }

    public function syncFromCatalog(array $categoryNames): void
    {
        $order = 0;
        foreach ($categoryNames as $name) {
            $name = strtoupper(trim($name));
            if ($name === '') {
                continue;
            }

            $defaults = self::DEFAULTS[$name] ?? ['icon' => '📄', 'color' => '#64748b', 'description' => null];
            $order += 10;

            AnamnesisCategoryDefinition::updateOrCreate(
                ['clinic_id' => null, 'slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'icon' => $defaults['icon'],
                    'icon_color' => $defaults['color'],
                    'description' => $defaults['description'],
                    'sort_order' => $order,
                    'is_active' => true,
                    'is_system' => true,
                ]
            );
        }
    }

    public function resolveId(?int $clinicId, string $categoryName): ?int
    {
        if (! Schema::hasTable('anamnesis_category_definitions')) {
            return null;
        }

        $name = strtoupper(trim($categoryName));
        $slug = Str::slug($name);

        $def = AnamnesisCategoryDefinition::query()
            ->forClinic($clinicId)
            ->where(function ($q) use ($slug, $name) {
                $q->where('slug', $slug)->orWhere('name', $name);
            })
            ->first();

        if ($def) {
            return $def->id;
        }

        $defaults = self::DEFAULTS[$name] ?? ['icon' => '📄', 'color' => '#64748b', 'description' => null];

        $def = AnamnesisCategoryDefinition::create([
            'clinic_id' => $clinicId,
            'name' => $name,
            'slug' => $slug . ($clinicId ? '-' . $clinicId : ''),
            'icon' => $defaults['icon'],
            'icon_color' => $defaults['color'],
            'description' => $defaults['description'],
            'sort_order' => (AnamnesisCategoryDefinition::forClinic($clinicId)->max('sort_order') ?? 0) + 10,
            'is_active' => true,
            'is_system' => false,
        ]);

        return $def->id;
    }

    public function store(array $data, ?int $clinicId): AnamnesisCategoryDefinition
    {
        return AnamnesisCategoryDefinition::create([
            'clinic_id' => $clinicId,
            'name' => strtoupper($data['name']),
            'slug' => Str::slug($data['name']) . ($clinicId ? '-' . $clinicId . '-' . Str::random(4) : '-' . Str::random(4)),
            'icon' => $data['icon'] ?? '📄',
            'icon_color' => $data['icon_color'] ?? '#64748b',
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? ((int) AnamnesisCategoryDefinition::forClinic($clinicId)->max('sort_order') + 10),
            'is_active' => $data['is_active'] ?? true,
            'is_system' => false,
        ]);
    }

    public function update(AnamnesisCategoryDefinition $category, array $data): AnamnesisCategoryDefinition
    {
        $category->update([
            'name' => strtoupper($data['name'] ?? $category->name),
            'icon' => $data['icon'] ?? $category->icon,
            'icon_color' => $data['icon_color'] ?? $category->icon_color,
            'description' => $data['description'] ?? $category->description,
            'sort_order' => $data['sort_order'] ?? $category->sort_order,
            'is_active' => $data['is_active'] ?? $category->is_active,
        ]);

        return $category->fresh();
    }

    public function serialize(AnamnesisCategoryDefinition $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'icon' => $c->icon,
            'icon_color' => $c->icon_color,
            'description' => $c->description,
            'sort_order' => $c->sort_order,
            'is_active' => $c->is_active,
            'is_system' => $c->is_system,
            'questions_count' => $c->questions()->count(),
        ];
    }
}