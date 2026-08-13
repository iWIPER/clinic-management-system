<?php

/**
 * Gera database/seeders/data/anamnese_wildental_export.md a partir de anamnese.txt
 * Uso: php database/seeders/ExportAnamnesisWildental.php
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$slugToModel = [
    'anamnese-adulta' => 'ADULTA',
    'anamnese-adulta-resumida' => 'ADULTA_RESUMIDA',
    'anamnese-infantil' => 'INFANTIL',
    'anamnese-infantil-resumida' => 'INFANTIL_RESUMIDA',
    'anamnese-hof' => 'HOF',
    'anamnese-ortodontica' => 'ORTODONTIA',
    'anamnese-ortodontica-resumida' => 'ORTODONTICA_RESUMIDA',
];

$typeMap = [
    'text' => 'TEXT',
    'yes_no' => 'YES_NO',
    'yes_no_text' => 'YES_NO_TEXT',
    'yes_no_unknown' => 'YES_NO_UNKNOWN',
    'yes_no_unknown_text' => 'YES_NO_UNKNOWN_TEXT',
];

$parser = new \App\Data\LegacyAnamneseTxtParser();
$catalog = $parser->buildCatalog(file_get_contents(database_path('seeders/data/anamnese.txt')));

$blocks = [];
foreach ($catalog['templates'] as $template) {
    $model = $slugToModel[$template['slug']] ?? 'ADULTA';

    foreach ($template['question_hashes'] as $hash) {
        $q = $catalog['questions'][$hash] ?? null;
        if (! $q) {
            continue;
        }

        $blocks[] = implode("\n", [
            'MODEL: ' . $model,
            'QUESTION: ' . $q['text'],
            'CATEGORY: ' . $q['category'],
            'TYPE: ' . ($typeMap[$q['type']] ?? 'TEXT'),
            'ALERT: ' . ($q['alert_text'] ?: 'NONE'),
            'SHOW_ON_PATIENT_CARD: ' . ($q['show_on_patient_card'] ? 'true' : 'false'),
        ]);
    }
}

$header = <<<'MD'
# Exportação Wildental (gerada automaticamente)

Revise categorias, remova duplicatas entre modelos e reorganize antes de importar.
Consulte `Padrao_Importacao_Anamnese_Wildental.md` para o formato oficial.

MD;

$content = $header . "\n" . implode("\n\n------------------------------------------------------------------------\n\n", $blocks) . "\n";
$out = database_path('seeders/data/anamnese_wildental_export.md');
file_put_contents($out, $content);

echo count($blocks) . " blocos exportados para {$out}\n";