<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCro implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $digits = preg_replace('/\D/', '', (string) $value);

        if (! preg_match('/^\d{4,6}$/', $digits)) {
            $fail('O CRO deve conter entre 4 e 6 dígitos.');
        }
    }
}