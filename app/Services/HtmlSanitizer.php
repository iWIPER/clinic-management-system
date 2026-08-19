<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitiza HTML de conteúdo rico (modelos de documento, evoluções clínicas)
 * antes de ele ser persistido — é a "fonte de verdade" da defesa contra XSS
 * armazenado: o mesmo HTML sanitizado aqui é o que depois é exibido via
 * v-html no frontend, embutido em PDF, e servido na página pública de
 * assinatura/validação de documento.
 *
 * A allowlist reflete exatamente o que o editor rico (Tiptap StarterKit +
 * Underline + TextAlign, ver DocumentRichEditor.vue) consegue produzir —
 * nada de link/imagem/script/iframe, pois o editor não oferece essas opções.
 * Usa ezyang/htmlpurifier, já vendorizado (dependência transitiva de
 * maatwebsite/excel) — nenhuma dependência nova foi adicionada ao projeto.
 */
class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function richText(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return self::purifier()->purify($html);
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        // Sem cache em disco — volume de conteúdo é baixo (salva por edição
        // de modelo/evolução, não por request de leitura) e evita depender
        // de um diretório gravável extra em produção.
        $config->set('Cache.DefinitionImpl', null);

        // Somente o que o editor rico realmente produz: formatação de texto,
        // títulos, listas, citação, e alinhamento via style (TextAlign).
        // Sem <a>/<img>/<script>/<iframe> — o editor não os oferece.
        $config->set('HTML.Allowed', implode(',', [
            'p[style]', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del',
            'h1[style]', 'h2[style]', 'h3[style]', 'h4[style]', 'h5[style]', 'h6[style]',
            'ul', 'ol', 'li', 'blockquote', 'hr', 'code', 'pre',
        ]));
        $config->set('CSS.AllowedProperties', ['text-align']);

        return self::$purifier = new HTMLPurifier($config);
    }
}
