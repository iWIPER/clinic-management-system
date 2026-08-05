<?php

namespace Database\Seeders;

use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

class DocumentTemplatesSeeder extends Seeder
{
    private const DATA_PATH = 'seeders/data/documents/';

    public function run(): void
    {
        foreach ($this->catalog() as $categoryData) {
            $category = DocumentCategory::updateOrCreate(
                ['slug' => $categoryData['slug'], 'clinic_id' => null],
                [
                    'name'        => $categoryData['name'],
                    'description' => $categoryData['description'],
                    'icon'        => $categoryData['icon'],
                    'color'       => $categoryData['color'],
                    'is_system'   => true,
                    'is_active'   => true,
                    'sort_order'  => $categoryData['sort_order'],
                ]
            );

            foreach ($categoryData['templates'] as $index => $templateData) {
                $contentPath = database_path(self::DATA_PATH . $templateData['file']);

                if (! file_exists($contentPath)) {
                    $this->command?->warn("Arquivo não encontrado: {$templateData['file']}");

                    continue;
                }

                $contentHtml = file_get_contents($contentPath);

                $template = DocumentTemplate::updateOrCreate(
                    ['slug' => $templateData['slug'], 'clinic_id' => null],
                    [
                        'category_id'                      => $category->id,
                        'name'                              => $templateData['name'],
                        'description'                       => $templateData['description'] ?? null,
                        'requires_patient_signature'        => $templateData['patient'] ?? true,
                        'requires_professional_signature'   => $templateData['professional'] ?? false,
                        'requires_responsible_signature'    => $templateData['responsible'] ?? false,
                        'requires_witness_signature'        => $templateData['witness'] ?? false,
                        'signature_expiration_hours'        => 72,
                        'is_system'                         => true,
                        'is_active'                          => true,
                        'is_default'                         => $templateData['default'] ?? false,
                        'sort_order'                         => $index,
                    ]
                );

                $currentContent = $template->currentVersion?->content_html;
                if ($currentContent !== $contentHtml) {
                    $template->createNewVersion($template->name, $contentHtml, 'Modelo padrão do sistema', null);
                }
            }
        }
    }

    private function catalog(): array
    {
        return [
            [
                'slug' => 'termos-consentimento', 'name' => 'Termos de Consentimento',
                'description' => 'Consentimentos informados e termos de aceite clínico', 'icon' => 'document-check', 'color' => 'teal', 'sort_order' => 1,
                'templates' => [
                    ['slug' => 'consentimento-lgpd', 'name' => 'Consentimento LGPD', 'file' => 'lgpd-consentimento.html', 'default' => true],
                    ['slug' => 'consentimento-informado', 'name' => 'Consentimento Informado', 'file' => 'consentimento-informado.html'],
                    ['slug' => 'consentimento-cirurgico', 'name' => 'Consentimento Cirúrgico', 'file' => 'consentimento-cirurgico.html'],
                    ['slug' => 'consentimento-implante', 'name' => 'Consentimento Implante', 'file' => 'consentimento-implante.html'],
                    ['slug' => 'consentimento-endodontia', 'name' => 'Consentimento Endodontia', 'file' => 'consentimento-endodontia.html'],
                    ['slug' => 'consentimento-ortodontia', 'name' => 'Consentimento Ortodontia', 'file' => 'consentimento-ortodontia.html', 'responsible' => true],
                ],
            ],
            [
                'slug' => 'contratos', 'name' => 'Contratos',
                'description' => 'Contratos de prestação de serviços e condições comerciais', 'icon' => 'document-text', 'color' => 'blue', 'sort_order' => 2,
                'templates' => [
                    ['slug' => 'contrato-prestacao-servicos', 'name' => 'Prestação de Serviços', 'file' => 'contrato-prestacao-servicos.html', 'default' => true],
                    ['slug' => 'contrato-plano-odontologico', 'name' => 'Plano Odontológico', 'file' => 'contrato-plano-odontologico.html'],
                    ['slug' => 'contrato-tratamento-parcelado', 'name' => 'Tratamento Parcelado', 'file' => 'contrato-tratamento-parcelado.html'],
                ],
            ],
            [
                'slug' => 'receitas', 'name' => 'Receitas',
                'description' => 'Receituários simples e de controle especial', 'icon' => 'clipboard-document', 'color' => 'emerald', 'sort_order' => 3,
                'templates' => [
                    ['slug' => 'receita-simples', 'name' => 'Receita Simples', 'file' => 'receita-simples.html', 'patient' => false, 'professional' => true, 'default' => true],
                    ['slug' => 'receita-controlada', 'name' => 'Receita Controlada', 'file' => 'receita-controlada.html', 'patient' => false, 'professional' => true],
                ],
            ],
            [
                'slug' => 'atestados', 'name' => 'Atestados',
                'description' => 'Atestados de comparecimento e afastamento', 'icon' => 'clipboard-document-check', 'color' => 'amber', 'sort_order' => 4,
                'templates' => [
                    ['slug' => 'atestado-comparecimento', 'name' => 'Atestado de Comparecimento', 'file' => 'atestado-comparecimento.html', 'patient' => false, 'professional' => true, 'default' => true],
                    ['slug' => 'atestado-afastamento', 'name' => 'Atestado de Afastamento', 'file' => 'atestado-afastamento.html', 'patient' => false, 'professional' => true],
                ],
            ],
            [
                'slug' => 'declaracoes', 'name' => 'Declarações',
                'description' => 'Declarações de tratamento e quitação financeira', 'icon' => 'document-duplicate', 'color' => 'purple', 'sort_order' => 5,
                'templates' => [
                    ['slug' => 'declaracao-tratamento', 'name' => 'Declaração de Tratamento', 'file' => 'declaracao-tratamento.html', 'patient' => false, 'professional' => true, 'default' => true],
                    ['slug' => 'declaracao-quitacao', 'name' => 'Declaração de Quitação', 'file' => 'declaracao-quitacao.html', 'patient' => false, 'professional' => true],
                ],
            ],
            [
                'slug' => 'encaminhamentos', 'name' => 'Encaminhamentos',
                'description' => 'Encaminhamentos para profissionais especialistas', 'icon' => 'arrow-right-circle', 'color' => 'pink', 'sort_order' => 6,
                'templates' => [
                    ['slug' => 'encaminhamento-especialista', 'name' => 'Encaminhamento para Especialista', 'file' => 'encaminhamento-especialista.html', 'patient' => false, 'professional' => true, 'default' => true],
                ],
            ],
            [
                'slug' => 'documentos-personalizados', 'name' => 'Documentos Personalizados',
                'description' => 'Modelos próprios criados pela clínica', 'icon' => 'sparkles', 'color' => 'slate', 'sort_order' => 7,
                'templates' => [],
            ],
        ];
    }
}
