<?php

use App\Services\BrazilianHolidayService;
use Illuminate\Support\Carbon;

test('fixed-date national holidays scale to any year, not just 2026', function () {
    foreach ([2020, 2025, 2030, 2040] as $year) {
        $holidays = BrazilianHolidayService::forYear($year);

        expect($holidays)->toHaveKey("{$year}-01-01");
        expect($holidays["{$year}-01-01"])->toBe('Confraternização Universal');
        expect($holidays)->toHaveKey("{$year}-12-25");
        expect($holidays["{$year}-12-25"])->toBe('Natal');
        expect($holidays)->toHaveKey("{$year}-09-07");
        expect($holidays)->toHaveKey("{$year}-05-01");
    }
});

test('Dia da Consciência Negra só é feriado nacional a partir de 2024 (Lei 14.759/2023)', function () {
    expect(BrazilianHolidayService::forYear(2023))->not->toHaveKey('2023-11-20');
    expect(BrazilianHolidayService::forYear(2024))->toHaveKey('2024-11-20');
    expect(BrazilianHolidayService::forYear(2030))->toHaveKey('2030-11-20');
});

test('Sexta-feira Santa é calculada a partir da Páscoa, não uma data fixa hardcoded', function () {
    // Valores conferidos contra a função nativa easter_date() do PHP.
    expect(BrazilianHolidayService::forYear(2024))->toHaveKey('2024-03-29');
    expect(BrazilianHolidayService::forYear(2025))->toHaveKey('2025-04-18');
    expect(BrazilianHolidayService::forYear(2026))->toHaveKey('2026-04-03');
    expect(BrazilianHolidayService::forYear(2027))->toHaveKey('2027-03-26');

    foreach ([2024, 2025, 2026, 2027, 2030] as $year) {
        $goodFriday = collect(BrazilianHolidayService::forYear($year))
            ->search('Sexta-feira Santa');
        expect(Carbon::parse($goodFriday)->isFriday())->toBeTrue();
    }
});

test('isHoliday() e nameFor() respondem certo pra data feriado e não-feriado', function () {
    $natal = Carbon::create(2030, 12, 25);
    $diaComum = Carbon::create(2030, 6, 10);

    expect(BrazilianHolidayService::isHoliday($natal))->toBeTrue();
    expect(BrazilianHolidayService::nameFor($natal))->toBe('Natal');
    expect(BrazilianHolidayService::isHoliday($diaComum))->toBeFalse();
    expect(BrazilianHolidayService::nameFor($diaComum))->toBeNull();
});

test('forRange() cruza a virada de ano corretamente e não traz nada fora do intervalo', function () {
    $range = BrazilianHolidayService::forRange(
        Carbon::create(2026, 12, 20),
        Carbon::create(2027, 1, 5)
    );

    expect($range)->toHaveKey('2026-12-25'); // Natal 2026
    expect($range)->toHaveKey('2027-01-01'); // Ano novo 2027
    expect($range)->not->toHaveKey('2026-11-20'); // fora do intervalo
    expect($range)->not->toHaveKey('2027-04-21'); // fora do intervalo
});
