<?php

namespace App\Services\Documents;

/**
 * Regra de normalização/comparação de nome para o fluxo de "confirme sua
 * identidade" do compartilhamento de documento (ver DocumentShare).
 *
 * Nome não é autenticação forte — é um filtro de sanidade contra erro/abuso
 * grosseiro, complementar ao CPF (o identificador forte de verdade) e ao
 * token do link em si (já não-adivinhável). Por isso a regra aceita nome
 * parcial ("Maria Luiza" para "Maria Luiza da Costa Silverio Rocha"), mas
 * rejeita entradas vagas demais para não virar um filtro inútil:
 *
 *   1. normaliza (minúsculas, sem acento, espaços colapsados);
 *   2. exige pelo menos 2 palavras submetidas, cada uma com 2+ caracteres;
 *   3. remove conectivos comuns (de/da/do/dos/das/e) da contagem — eles não
 *      contam como palavra "real" para o mínimo de 2;
 *   4. toda palavra "real" submetida precisa bater, como palavra inteira,
 *      com alguma palavra do nome completo cadastrado (correspondência por
 *      conjunto, não por ordem — cobre "Maria Rocha" tanto quanto
 *      "Maria Luiza", mas rejeita "da Rocha" por ter só 1 palavra real).
 */
class PatientIdentityMatcher
{
    private const CONNECTORS = ['de', 'da', 'do', 'das', 'dos', 'e'];

    public static function namesMatch(string $registeredFullName, string $submittedName): bool
    {
        $registeredWords = self::normalizedWords($registeredFullName);
        $submittedWords = self::normalizedWords($submittedName);

        $submittedRealWords = array_values(array_filter(
            $submittedWords,
            fn (string $w) => ! in_array($w, self::CONNECTORS, true) && mb_strlen($w) >= 2
        ));

        if (count($submittedRealWords) < 2) {
            return false;
        }

        foreach ($submittedRealWords as $word) {
            if (! in_array($word, $registeredWords, true)) {
                return false;
            }
        }

        return true;
    }

    private static function normalizedWords(string $value): array
    {
        $value = mb_strtolower(trim($value));
        $value = self::stripAccents($value);
        $value = preg_replace('/[^a-z\s]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return array_values(array_filter(explode(' ', trim($value))));
    }

    private static function stripAccents(string $value): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return $transliterated !== false ? $transliterated : $value;
    }
}
