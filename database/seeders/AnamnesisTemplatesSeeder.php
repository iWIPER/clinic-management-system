<?php

namespace Database\Seeders;

use App\Data\AnamnesisImportParser;
use App\Data\LegacyAnamneseTxtParser;
use App\Models\AnamnesisQuestion;
use App\Models\AnamnesisTemplate;
use App\Models\AnamnesisTemplateQuestion;
use App\Models\PatientTag;
use App\Services\Anamnesis\CategoryDefinitionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AnamnesisTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $clinicFlowPath = database_path('seeders/data/anamnese_padronizada_clinicflow.txt');
        $legacyPath = database_path('seeders/data/anamnese.txt');

        if (file_exists($clinicFlowPath)) {
            $catalog = (new AnamnesisImportParser())->buildCatalog(file_get_contents($clinicFlowPath));
        } elseif (file_exists($legacyPath)) {
            $catalog = (new LegacyAnamneseTxtParser())->buildCatalog(file_get_contents($legacyPath));
        } else {
            $this->command?->error('Arquivo de anamnese não encontrado.');

            return;
        }

        $categoryService = app(CategoryDefinitionService::class);
        $categoryNames = collect($catalog['questions'])->pluck('category')->unique()->filter()->values()->all();
        $categoryService->syncFromCatalog($categoryNames);

        DB::transaction(function () use ($catalog, $categoryService) {
            $hashToId = [];

            foreach ($catalog['questions'] as $hash => $questionData) {
                if (! $this->isValidQuestionText($questionData['text'])) {
                    continue;
                }

                $categoryId = $categoryService->resolveId(null, $questionData['category']);

                $question = AnamnesisQuestion::updateOrCreate(
                    ['question_hash' => $hash],
                    [
                        'clinic_id' => null,
                        'category_id' => $categoryId,
                        'category' => $questionData['category'],
                        'text' => $questionData['text'],
                        'description' => null,
                        'type' => $questionData['type'],
                        'is_required' => $questionData['is_required'],
                        'has_alert' => $questionData['has_alert'],
                        'alert_text' => $questionData['alert_text'],
                        'alert_trigger_values' => $questionData['alert_trigger_values'],
                        'show_on_patient_card' => $questionData['show_on_patient_card'],
                        'is_active' => $questionData['is_active'],
                    ]
                );

                $hashToId[$hash] = $question->id;
            }

            AnamnesisQuestion::query()
                ->whereNull('clinic_id')
                ->where('is_active', true)
                ->get()
                ->each(function (AnamnesisQuestion $question) {
                    if (! $question->isRenderable()) {
                        $question->update(['is_active' => false]);
                    }
                });

            foreach ($catalog['templates'] as $templateData) {
                $template = AnamnesisTemplate::updateOrCreate(
                    ['slug' => $templateData['slug'], 'clinic_id' => null],
                    [
                        'name' => $templateData['name'],
                        'description' => 'Modelo padrão do sistema',
                        'version' => 1,
                        'is_system' => true,
                        'is_active' => true,
                        'is_default' => $templateData['slug'] === 'anamnese-adulta',
                        'sort_order' => $templateData['sort_order'],
                    ]
                );

                AnamnesisTemplateQuestion::query()
                    ->where('template_id', $template->id)
                    ->delete();

                foreach ($templateData['question_hashes'] as $index => $hash) {
                    if (! isset($hashToId[$hash])) {
                        continue;
                    }

                    AnamnesisTemplateQuestion::create([
                        'template_id' => $template->id,
                        'question_id' => $hashToId[$hash],
                        'sort_order' => $index + 1,
                        'is_required' => false,
                    ]);
                }
            }
        });

        $this->seedDefaultTags();
        $this->seedDefaultMarkers();
    }

    private function isValidQuestionText(string $text): bool
    {
        return (new AnamnesisQuestion(['text' => $text]))->isRenderable();
    }

    private function deactivateMetadataQuestions(): void
    {
        AnamnesisQuestion::query()
            ->whereNull('clinic_id')
            ->where('is_active', true)
            ->get()
            ->each(function (AnamnesisQuestion $question) {
                if (! $question->isRenderable()) {
                    $question->update(['is_active' => false]);
                }
            });
    }

    private function seedDefaultTags(): void
    {
        $tags = [
            ['name' => 'Urgente', 'color' => '#ef4444'],
            ['name' => 'Financeiro', 'color' => '#f59e0b'],
            ['name' => 'Cirurgia', 'color' => '#8b5cf6'],
            ['name' => 'Implante', 'color' => '#06b6d4'],
            ['name' => 'Prótese', 'color' => '#3b82f6'],
            ['name' => 'Ortodontia', 'color' => '#6366f1'],
            ['name' => 'HOF', 'color' => '#ec4899'],
            ['name' => 'Ansiedade', 'color' => '#f97316'],
            ['name' => 'Infantil', 'color' => '#22c55e'],
            ['name' => 'Convênio', 'color' => '#14b8a6'],
            ['name' => 'Documentação', 'color' => '#64748b'],
            ['name' => 'Retorno', 'color' => '#0ea5e9'],
            ['name' => 'Controle', 'color' => '#84cc16'],
            ['name' => 'Radiografia', 'color' => '#a855f7'],
            ['name' => 'Medicamentos', 'color' => '#e11d48'],
        ];

        foreach ($tags as $tag) {
            PatientTag::updateOrCreate(
                ['clinic_id' => null, 'slug' => \Illuminate\Support\Str::slug($tag['name'])],
                [
                    'name' => $tag['name'],
                    'color' => $tag['color'],
                    'is_system' => true,
                ]
            );
        }
    }

    /**
     * Marcadores administrativos do paciente — mesmo vocabulário de
     * PatientTag acima (is_patient_marker=true), não uma segunda lista.
     * Observações reutilizam exatamente estes registros. "Falecido"/"Inativo"
     * não entram aqui de propósito: já existem como valores de
     * Patient::status, um marcador manual duplicaria essa informação.
     */
    private function seedDefaultMarkers(): void
    {
        // Sem equivalente prévio como categoria de nota.
        $newMarkers = [
            ['name' => 'Link de Agendamento', 'color' => '#0ea5e9'],
            ['name' => 'Parceiro', 'color' => '#8b5cf6'],
        ];

        foreach ($newMarkers as $marker) {
            PatientTag::updateOrCreate(
                ['clinic_id' => null, 'slug' => \Illuminate\Support\Str::slug($marker['name'])],
                [
                    'name' => $marker['name'],
                    'color' => $marker['color'],
                    'is_system' => true,
                    'is_patient_marker' => true,
                ]
            );
        }

        // Promove categorias de observação já existentes para marcador —
        // mesmo registro, mesma cor; passam a valer também como
        // característica do paciente, sem criar uma segunda entidade.
        PatientTag::query()
            ->whereNull('clinic_id')
            ->whereIn('slug', ['ortodontia', 'hof', 'implante', 'protese'])
            ->update(['is_patient_marker' => true]);
    }
}