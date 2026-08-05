<?php

namespace App\Data;

use App\Enums\Anamnesis\QuestionType;

class AnamnesisImportParser
{
    private const MODEL_MAP = [
        'ADULTA' => ['name' => 'Anamnese Adulta', 'slug' => 'anamnese-adulta', 'sort' => 1],
        'ADULTA_RESUMIDA' => ['name' => 'Anamnese Adulta Resumida', 'slug' => 'anamnese-adulta-resumida', 'sort' => 2],
        'INFANTIL' => ['name' => 'Anamnese Infantil', 'slug' => 'anamnese-infantil', 'sort' => 3],
        'INFANTIL_RESUMIDA' => ['name' => 'Anamnese Infantil Resumida', 'slug' => 'anamnese-infantil-resumida', 'sort' => 4],
        'HOF' => ['name' => 'Anamnese HOF', 'slug' => 'anamnese-hof', 'sort' => 5],
        'ORTODONTIA' => ['name' => 'Anamnese Ortodôntica', 'slug' => 'anamnese-ortodontica', 'sort' => 6],
        'ORTODONTICA' => ['name' => 'Anamnese Ortodôntica', 'slug' => 'anamnese-ortodontica', 'sort' => 6],
        'ORTODONTICA_RESUMIDA' => ['name' => 'Anamnese Ortodôntica Resumida', 'slug' => 'anamnese-ortodontica-resumida', 'sort' => 7],
        'ORTODONTIA_RESUMIDA' => ['name' => 'Anamnese Ortodôntica Resumida', 'slug' => 'anamnese-ortodontica-resumida', 'sort' => 7],
    ];

    /** @return array<int, array{model: string, question: string, category: string, type: string, alert: ?string, show_on_patient_card: bool}> */
    public function parseBlocks(string $content): array
    {
        $blocks = preg_split('/\n-{3,}\n|\n={3,}\n/', $content) ?: [];
        $entries = [];

        foreach ($blocks as $block) {
            $parsed = $this->parseBlock(trim($block));
            if ($parsed) {
                $entries[] = $parsed;
            }
        }

        return $entries;
    }

    /** @return array{templates: array, questions: array<string, array>} */
    public function buildCatalog(string $content): array
    {
        $entries = $this->parseBlocks($content);
        $questions = [];
        $templates = [];

        foreach ($entries as $entry) {
            $hash = $this->hash($entry['question']);
            $payload = [
                'text' => $entry['question'],
                'category' => $entry['category'],
                'type' => $entry['type'],
                'has_alert' => filled($entry['alert']),
                'alert_text' => $entry['alert'],
                'alert_trigger_values' => filled($entry['alert']) ? ['sim'] : null,
                'show_on_patient_card' => $entry['show_on_patient_card'],
                'is_required' => false,
                'is_active' => true,
            ];

            if (! isset($questions[$hash])) {
                $questions[$hash] = $payload;
            } else {
                $questions[$hash] = array_merge($questions[$hash], $payload);
            }

            $modelKey = strtoupper($entry['model']);
            if (! isset($templates[$modelKey])) {
                $meta = self::MODEL_MAP[$modelKey] ?? null;
                if (! $meta) {
                    continue;
                }
                $templates[$modelKey] = [
                    'name' => $meta['name'],
                    'slug' => $meta['slug'],
                    'sort_order' => $meta['sort'],
                    'question_hashes' => [],
                ];
            }

            if (! in_array($hash, $templates[$modelKey]['question_hashes'], true)) {
                $templates[$modelKey]['question_hashes'][] = $hash;
            }
        }

        return [
            'questions' => $questions,
            'templates' => array_values($templates),
        ];
    }

    private function parseBlock(string $block): ?array
    {
        if ($block === '' || str_starts_with($block, '#')) {
            return null;
        }

        $fields = [];
        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '•')) {
                continue;
            }

            if (preg_match('/^(MODEL|QUESTION|CATEGORY|TYPE|ALERT|SHOW_ON_PATIENT_CARD)\s*:\s*(.*)$/iu', $line, $m)) {
                $fields[strtoupper($m[1])] = trim($m[2]);
            }
        }

        if (empty($fields['QUESTION']) || empty($fields['MODEL'])) {
            return null;
        }

        $type = QuestionType::fromImport($fields['TYPE'] ?? 'TEXT');

        return [
            'model' => strtoupper($fields['MODEL']),
            'question' => $fields['QUESTION'],
            'category' => strtoupper($fields['CATEGORY'] ?? 'GERAL'),
            'type' => $type->value,
            'alert' => $this->normalizeAlert($fields['ALERT'] ?? 'NONE'),
            'show_on_patient_card' => $this->toBool($fields['SHOW_ON_PATIENT_CARD'] ?? 'true'),
        ];
    }

    private function normalizeAlert(?string $alert): ?string
    {
        if (! $alert || strtoupper($alert) === 'NONE' || strtoupper($alert) === 'NENHUM') {
            return null;
        }

        return $alert;
    }

    private function toBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['true', 'sim', 'yes', '1'], true);
    }

    public function hash(string $text): string
    {
        return hash('sha256', mb_strtolower(trim($text)));
    }
}