<?php

namespace App\Data;

use App\Enums\Anamnesis\QuestionType;

/**
 * Parser corrigido para o arquivo legado anamnese.txt.
 * Metadados (alerta, tipo, visibilidade) nunca viram perguntas.
 */
class LegacyAnamneseTxtParser
{
    private const TEMPLATE_MARKERS = [
        '-- Anamnese adulta resumida --' => 'ADULTA_RESUMIDA',
        '-- Anamnese HOF --' => 'HOF',
        '-- Anamnese infantil resumida --' => 'INFANTIL_RESUMIDA',
        '-- Anamnese infantil --' => 'INFANTIL',
        '-- Anamnese ortodôntica resumida --' => 'ORTODONTICA_RESUMIDA',
        '-- Anamnese ortodôntica --' => 'ORTODONTIA',
        '-- Anamnese adulta --' => null,
        '-- Modelos de anamnese (criar) --' => null,
    ];

    public function buildCatalog(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $currentModel = 'ADULTA';
        $block = null;
        $entries = [];

        $flush = function () use (&$block, &$entries, &$currentModel) {
            if (! $block || empty($block['question'])) {
                $block = null;
                return;
            }

            $text = trim($block['question']);
            if ($this->isMetadata($text)) {
                $block = null;
                return;
            }

            $type = QuestionType::fromLegacyDocument($block['type_raw'] ?? 'Pergunta Somente Texto');

            $entries[] = [
                'model' => $currentModel,
                'question' => $text,
                'category' => $this->guessCategory($text),
                'type' => $type->value,
                'alert' => $this->parseAlert($block['alert_raw'] ?? null),
                'show_on_patient_card' => $block['show_on_card'] ?? true,
            ];
            $block = null;
        };

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            if ($this->isSkippable($line)) {
                continue;
            }

            if ($this->matchTemplateMarker($line, $model)) {
                $flush();
                if ($model) {
                    $currentModel = $model;
                }
                continue;
            }

            if ($this->isMetadata($line)) {
                if (! $block) {
                    continue;
                }
                $this->applyMetadata($block, $line);
                continue;
            }

            $flush();
            $block = [
                'question' => ltrim($line, '. '),
                'alert_raw' => null,
                'type_raw' => null,
                'show_on_card' => true,
            ];
        }

        $flush();

        return (new AnamnesisImportParser())->buildCatalog($this->entriesToImportFormat($entries));
    }

    /** @param array<int, array> $entries */
    private function entriesToImportFormat(array $entries): string
    {
        $chunks = [];
        foreach ($entries as $e) {
            $chunks[] = implode("\n", [
                'MODEL: ' . $e['model'],
                'QUESTION: ' . $e['question'],
                'CATEGORY: ' . $e['category'],
                'TYPE: ' . strtoupper($e['type']),
                'ALERT: ' . ($e['alert'] ?: 'NONE'),
                'SHOW_ON_PATIENT_CARD: ' . ($e['show_on_patient_card'] ? 'true' : 'false'),
            ]);
        }

        return implode("\n\n------------------------------------------------------------------------\n\n", $chunks);
    }

    private function isSkippable(string $line): bool
    {
        return $line === ''
            || $line === 'Checkpoint'
            || preg_match('/^-{5,}$/', $line)
            || str_starts_with($line, 'Para todos os outros');
    }

    private function matchTemplateMarker(string $line, ?string &$model): bool
    {
        foreach (self::TEMPLATE_MARKERS as $marker => $name) {
            if (strcasecmp($line, $marker) === 0) {
                $model = $name;
                return true;
            }
        }

        $model = null;
        return false;
    }

    private function stripDecorators(string $line): string
    {
        $line = trim($line);
        $line = preg_replace('/^[-.]+\s*/', '', $line) ?? $line;
        $line = preg_replace('/^Alerta:\s*/iu', '', $line) ?? $line;

        return trim($line);
    }

    private function isMetadata(string $line): bool
    {
        $normalized = $this->stripDecorators($line);

        if ($normalized === '') {
            return true;
        }

        $patterns = [
            '/^(Sem alerta|Com alerta)/iu',
            '/^Pergunta\s/iu',
            '/^(Disponível na ficha|Não aparece|Não disponível)/iu',
            '/^Checkpoint$/i',
            '/^-{5,}/',
            '/^--\s*.+\s*--$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return true;
            }
        }

        return false;
    }

    private function applyMetadata(array &$block, string $line): void
    {
        if (str_contains($line, '\\')) {
            foreach (explode('\\', $line) as $part) {
                $this->applyMetadataPart($block, trim($part));
            }

            return;
        }

        $this->applyMetadataPart($block, $line);
    }

    private function applyMetadataPart(array &$block, string $line): void
    {
        $normalized = $this->stripDecorators($line);

        if ($normalized === '') {
            return;
        }

        if (preg_match('/^com alerta:\s*(.+)$/iu', $normalized, $m)) {
            $block['alert_raw'] = trim($m[1]);
        } elseif (preg_match('/^sem alerta/iu', $normalized)) {
            $block['alert_raw'] = null;
        } elseif (preg_match('/^Pergunta\s+(.+)$/iu', $normalized, $m)) {
            $block['type_raw'] = 'Pergunta ' . trim($m[1]);
        } elseif (stripos($normalized, 'não aparece') !== false || stripos($normalized, 'não disponível') !== false) {
            $block['show_on_card'] = false;
        } elseif (stripos($normalized, 'disponível na ficha') !== false) {
            $block['show_on_card'] = true;
        }
    }

    private function parseAlert(?string $raw): ?string
    {
        if (! $raw || str_contains(mb_strtolower($raw), 'sem alerta')) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === 'undefined') {
            return null;
        }

        if (preg_match('/alérgico a/i', $raw)) {
            return 'Alérgico a';
        }

        return $raw;
    }

    private function guessCategory(string $question): string
    {
        $map = [
            'queixa' => 'QUEIXA PRINCIPAL',
            'dor nos dentes' => 'QUEIXA PRINCIPAL',
            'medicação' => 'DOENÇAS SISTÊMICAS',
            'alergia' => 'DOENÇAS SISTÊMICAS',
            'diabetes' => 'DOENÇAS SISTÊMICAS',
            'pressão alta' => 'DOENÇAS SISTÊMICAS',
            'asma' => 'DOENÇAS SISTÊMICAS',
            'hemorragia' => 'DOENÇAS SISTÊMICAS',
            'gravida' => 'GESTAÇÃO',
            'grávida' => 'GESTAÇÃO',
            'escova' => 'HÁBITOS',
            'fio dental' => 'HÁBITOS',
            'fumante' => 'HÁBITOS',
            'última vez' => 'HISTÓRICO',
            'dentista' => 'HISTÓRICO',
            'ortodontia' => 'ODONTOLÓGICO',
            'cirurgia oral' => 'ODONTOLÓGICO',
            'perfil' => 'ORTODONTIA',
            'mordida' => 'ORTODONTIA',
            'canino' => 'ORTODONTIA',
            'febre' => 'COVID',
            '2019-ncov' => 'COVID',
            'máscara' => 'COVID',
            'pele' => 'ESTÉTICA',
            'rosto' => 'ESTÉTICA',
            'filtro solar' => 'ESTÉTICA',
            'parto' => 'PEDIATRIA',
            'aleitamento' => 'PEDIATRIA',
            'mamadeira' => 'PEDIATRIA',
            'pressão arterial' => 'EXAME CLÍNICO',
            'frequência' => 'EXAME CLÍNICO',
        ];

        $lower = mb_strtolower($question);
        foreach ($map as $needle => $category) {
            if (str_contains($lower, $needle)) {
                return $category;
            }
        }

        return 'GERAL';
    }
}