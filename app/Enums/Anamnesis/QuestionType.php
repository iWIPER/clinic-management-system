<?php

namespace App\Enums\Anamnesis;

enum QuestionType: string
{
    case Text = 'text';
    case YesNo = 'yes_no';
    case YesNoText = 'yes_no_text';
    case YesNoUnknown = 'yes_no_unknown';
    case YesNoUnknownText = 'yes_no_unknown_text';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Somente Texto',
            self::YesNo => 'Sim / Não',
            self::YesNoText => 'Sim / Não + Texto',
            self::YesNoUnknown => 'Sim / Não / Não sei',
            self::YesNoUnknownText => 'Sim / Não / Não sei + Texto',
        };
    }

    public function hasSupplementaryOnSimOnly(): bool
    {
        return in_array($this, [self::YesNoText, self::YesNoUnknownText], true);
    }

    public static function fromImport(string $raw): self
    {
        return match (strtoupper(trim($raw))) {
            'TEXT', 'TEXTO' => self::Text,
            'YES_NO', 'SIM_NAO' => self::YesNo,
            'YES_NO_TEXT', 'SIM_NAO_TEXTO' => self::YesNoText,
            'YES_NO_UNKNOWN', 'SIM_NAO_NAO_SEI' => self::YesNoUnknown,
            'YES_NO_UNKNOWN_TEXT', 'SIM_NAO_NAO_SEI_TEXTO' => self::YesNoUnknownText,
            default => self::fromLegacyDocument($raw),
        };
    }

    public static function fromLegacyDocument(string $raw): self
    {
        $n = mb_strtolower(trim($raw));

        if (str_contains($n, 'somente texto')) {
            return self::Text;
        }

        if (str_contains($n, 'sim/não/não sei') && str_contains($n, 'texto')) {
            return self::YesNoUnknownText;
        }

        if (str_contains($n, 'sim/não') && str_contains($n, 'texto')) {
            return self::YesNoText;
        }

        if (str_contains($n, 'sim/não/não se')) {
            return self::YesNoUnknown;
        }

        if (str_contains($n, 'sim/não')) {
            return self::YesNo;
        }

        return self::Text;
    }
}