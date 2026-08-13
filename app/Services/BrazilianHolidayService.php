<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Fonte única de feriados nacionais brasileiros — nenhuma data deve ficar
 * hardcoded em controller/model/frontend. Funciona pra qualquer ano (datas
 * fixas escalam trivialmente; a Sexta-feira Santa é calculada a partir da
 * Páscoa daquele ano, não de uma tabela).
 *
 * Carnaval e Corpus Christi são "pontos facultativos" federais, não
 * feriados nacionais obrigatórios por lei — de propósito NÃO estão nesta
 * lista. Se a clínica quiser tratá-los como feriado também, isso é uma
 * decisão de produto separada, não implementada aqui.
 */
class BrazilianHolidayService
{
    /** Feriados nacionais de data fixa — chave "MM-DD". */
    private const FIXED = [
        '01-01' => 'Confraternização Universal',
        '04-21' => 'Tiradentes',
        '05-01' => 'Dia do Trabalho',
        '09-07' => 'Independência do Brasil',
        '10-12' => 'Nossa Senhora Aparecida',
        '11-02' => 'Finados',
        '11-15' => 'Proclamação da República',
        '12-25' => 'Natal',
    ];

    /** Lei 14.759/2023 — feriado nacional a partir de 2024. */
    private const CONSCIENCIA_NEGRA_LABEL = 'Dia Nacional de Zumbi e da Consciência Negra';
    private const CONSCIENCIA_NEGRA_FROM_YEAR = 2024;

    /** @return array<string, string> mapa "Y-m-d" => nome do feriado */
    public static function forYear(int $year): array
    {
        $holidays = [];

        foreach (self::FIXED as $monthDay => $name) {
            $holidays["{$year}-{$monthDay}"] = $name;
        }

        if ($year >= self::CONSCIENCIA_NEGRA_FROM_YEAR) {
            $holidays["{$year}-11-20"] = self::CONSCIENCIA_NEGRA_LABEL;
        }

        $goodFriday = self::easterDate($year)->subDays(2);
        $holidays[$goodFriday->format('Y-m-d')] = 'Sexta-feira Santa';

        ksort($holidays);

        return $holidays;
    }

    /** @return array<string, string> mapa "Y-m-d" => nome, só o intervalo pedido */
    public static function forRange(Carbon $start, Carbon $end): array
    {
        $holidays = [];
        foreach (range($start->year, $end->year) as $year) {
            $holidays += self::forYear($year);
        }

        $startStr = $start->format('Y-m-d');
        $endStr = $end->format('Y-m-d');

        return array_filter(
            $holidays,
            fn ($date) => $date >= $startStr && $date <= $endStr,
            ARRAY_FILTER_USE_KEY
        );
    }

    public static function isHoliday(\DateTimeInterface $date): bool
    {
        return self::nameFor($date) !== null;
    }

    public static function nameFor(\DateTimeInterface $date): ?string
    {
        $year = (int) $date->format('Y');

        return self::forYear($year)[$date->format('Y-m-d')] ?? null;
    }

    /**
     * Data da Páscoa pelo algoritmo anônimo/gregoriano — não depende de
     * ext-calendar (easter_date() nem sempre está disponível e tem
     * limitações de ano em sistemas 32-bit). Validado contra easter_date()
     * pra 2024–2030 antes de entrar em produção.
     */
    private static function easterDate(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::createFromDate($year, $month, $day)->startOfDay();
    }
}
