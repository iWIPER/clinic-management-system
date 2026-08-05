<?php

namespace App\Services\Documents;

use App\Models\Budget;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;

/**
 * Resolve placeholders %token% em conteúdo HTML de modelos/documentos a partir
 * do contexto (paciente, clínica, profissional, tratamento, orçamento).
 *
 * Fonte única de verdade tanto para o preview ao vivo do editor quanto para a
 * emissão final do documento — availablePlaceholders() alimenta o menu de
 * inserção no editor.
 */
class DocumentPlaceholderResolver
{
    /**
     * %assinatura-paciente% e %assinatura-profissional% não viram texto aqui —
     * são resolvidos visualmente (caixa pontilhada no preview, bloco real no PDF).
     */
    public const SIGNATURE_PLACEHOLDERS = [
        'assinatura-paciente',
        'assinatura-profissional',
        'assinatura-responsavel',
        'assinatura-testemunha',
    ];

    public function resolve(string $html, array $context, bool $forPreview = false): string
    {
        return preg_replace_callback('/%([a-z0-9\-]+)%/i', function (array $matches) use ($context, $forPreview) {
            $key = strtolower($matches[1]);

            if (in_array($key, self::SIGNATURE_PLACEHOLDERS, true)) {
                return $forPreview ? $this->signaturePlaceholderPreview($key) : $matches[0];
            }

            $map = $this->map();

            if (! isset($map[$key])) {
                return $matches[0];
            }

            return (string) $map[$key]($context);
        }, $html);
    }

    private function signaturePlaceholderPreview(string $key): string
    {
        $label = match ($key) {
            'assinatura-paciente'     => 'Assinatura do Paciente',
            'assinatura-profissional' => 'Assinatura do Profissional',
            'assinatura-responsavel'  => 'Assinatura do Responsável',
            'assinatura-testemunha'   => 'Assinatura da Testemunha',
            default                   => 'Assinatura',
        };

        return '<span style="display:inline-block;border:1px dashed #94a3b8;border-radius:6px;padding:10px 18px;color:#64748b;font-size:12px;">'
            . $label . '</span>';
    }

    /**
     * Usado apenas na geração final do PDF: substitui %assinatura-x% pelo bloco
     * real (nome + linha + imagem, ou "aguardando assinatura").
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\DocumentSignature> $signatures
     */
    public function resolveSignatureBlocksForPdf(string $html, \Illuminate\Support\Collection $signatures): string
    {
        return preg_replace_callback('/%(assinatura-[a-z]+)%/i', function (array $m) use ($signatures) {
            $role = match (strtolower($m[1])) {
                'assinatura-paciente'     => 'patient',
                'assinatura-profissional' => 'professional',
                'assinatura-responsavel'  => 'responsible',
                'assinatura-testemunha'   => 'witness',
                default                   => null,
            };

            if (! $role) {
                return $m[0];
            }

            $signature = $signatures->firstWhere('signer_role', $role);

            return view('pdf.partials.document-signature-block', [
                'signature' => $signature,
                'role'      => $role,
            ])->render();
        }, $html);
    }

    public function availablePlaceholders(): array
    {
        return [
            ['key' => 'nome-paciente', 'label' => 'Nome do paciente', 'group' => 'Paciente'],
            ['key' => 'cpf', 'label' => 'CPF', 'group' => 'Paciente'],
            ['key' => 'rg', 'label' => 'RG', 'group' => 'Paciente'],
            ['key' => 'telefone', 'label' => 'Telefone', 'group' => 'Paciente'],
            ['key' => 'email', 'label' => 'E-mail', 'group' => 'Paciente'],
            ['key' => 'idade', 'label' => 'Idade', 'group' => 'Paciente'],
            ['key' => 'sexo', 'label' => 'Sexo', 'group' => 'Paciente'],
            ['key' => 'endereco', 'label' => 'Endereço', 'group' => 'Paciente'],
            ['key' => 'cidade', 'label' => 'Cidade', 'group' => 'Paciente'],
            ['key' => 'estado', 'label' => 'Estado', 'group' => 'Paciente'],
            ['key' => 'cep', 'label' => 'CEP', 'group' => 'Paciente'],

            ['key' => 'nome-clinica', 'label' => 'Nome da clínica', 'group' => 'Clínica'],
            ['key' => 'cnpj', 'label' => 'CNPJ', 'group' => 'Clínica'],
            ['key' => 'local', 'label' => 'Local (clínica/cidade)', 'group' => 'Clínica'],

            ['key' => 'dentista', 'label' => 'Nome do profissional', 'group' => 'Profissional'],
            ['key' => 'cro', 'label' => 'CRO do profissional', 'group' => 'Profissional'],
            ['key' => 'especialidade', 'label' => 'Especialidade', 'group' => 'Profissional'],

            ['key' => 'tratamento', 'label' => 'Tratamento', 'group' => 'Tratamento'],
            ['key' => 'valor', 'label' => 'Valor (orçamento)', 'group' => 'Tratamento'],

            ['key' => 'data', 'label' => 'Data de hoje', 'group' => 'Data/Hora'],
            ['key' => 'hora', 'label' => 'Hora atual', 'group' => 'Data/Hora'],

            ['key' => 'assinatura-paciente', 'label' => 'Assinatura do paciente', 'group' => 'Assinaturas'],
            ['key' => 'assinatura-profissional', 'label' => 'Assinatura do profissional', 'group' => 'Assinaturas'],
            ['key' => 'assinatura-responsavel', 'label' => 'Assinatura do responsável', 'group' => 'Assinaturas'],
            ['key' => 'assinatura-testemunha', 'label' => 'Assinatura da testemunha', 'group' => 'Assinaturas'],
        ];
    }

    /**
     * @return array<string, callable(array): string>
     */
    private function map(): array
    {
        return [
            'nome-paciente' => function (array $ctx) {
                /** @var ?Patient $p */
                $p = $ctx['patient'] ?? null;

                return $p?->nome_completo ?? '';
            },
            'cpf' => function (array $ctx) {
                $p = $ctx['patient'] ?? null;

                return ($p && $p->doc_tipo === 'cpf') ? (string) $p->doc_numero : '';
            },
            'rg' => function (array $ctx) {
                $p = $ctx['patient'] ?? null;

                return ($p && $p->doc_tipo === 'rg') ? (string) $p->doc_numero : '';
            },
            'telefone' => fn (array $ctx) => $ctx['patient']?->telefone ?? '',
            'email'    => fn (array $ctx) => $ctx['patient']?->email ?? '',
            'idade'    => function (array $ctx) {
                /** @var ?Patient $p */
                $p = $ctx['patient'] ?? null;

                return $p?->nascimento ? $p->nascimento->age . ' anos' : '';
            },
            'sexo' => fn (array $ctx) => $ctx['patient']?->sexo ?? '',
            'endereco' => function (array $ctx) {
                /** @var ?Patient $p */
                $p = $ctx['patient'] ?? null;
                if (! $p) {
                    return '';
                }

                $parts = array_filter([
                    $p->logradouro && $p->numero ? "{$p->logradouro}, {$p->numero}" : $p->logradouro,
                    $p->complemento,
                    $p->bairro,
                ]);

                return implode(', ', $parts);
            },
            'cidade' => fn (array $ctx) => $ctx['patient']?->cidade ?? '',
            'estado' => fn (array $ctx) => $ctx['patient']?->estado ?? '',
            'cep'    => fn (array $ctx) => $ctx['patient']?->cep ?? '',

            'nome-clinica' => function (array $ctx) {
                /** @var ?Clinic $c */
                $c = $ctx['clinic'] ?? null;

                return $c?->displayName() ?? '';
            },
            'cnpj' => fn (array $ctx) => $ctx['clinic']?->cnpj ?? '',
            'local' => function (array $ctx) {
                /** @var ?Clinic $c */
                $c = $ctx['clinic'] ?? null;
                if (! $c) {
                    return '';
                }

                return $c->address_city ? "{$c->displayName()} — {$c->address_city}" : $c->displayName();
            },

            'dentista' => fn (array $ctx) => $ctx['professional']?->name ?? '',
            'cro' => function (array $ctx) {
                /** @var ?User $u */
                $u = $ctx['professional'] ?? null;
                if (! $u || ! $u->cro) {
                    return '';
                }

                return $u->cro . ($u->cro_uf ? '/' . $u->cro_uf : '');
            },
            'especialidade' => function (array $ctx) {
                /** @var ?Treatment $t */
                $t = $ctx['treatment'] ?? null;
                /** @var ?User $u */
                $u = $ctx['professional'] ?? null;

                return $t?->especialidade ?: ($u?->specialty ?? '');
            },

            'tratamento' => function (array $ctx) {
                /** @var ?Treatment $t */
                $t = $ctx['treatment'] ?? null;

                return $t?->nome ?? '';
            },
            'valor' => function (array $ctx) {
                /** @var ?Budget $b */
                $b = $ctx['budget'] ?? null;

                return $b ? 'R$ ' . number_format((float) $b->total, 2, ',', '.') : '';
            },

            'data' => fn () => now()->format('d/m/Y'),
            'hora' => fn () => now()->format('H:i'),
        ];
    }
}
