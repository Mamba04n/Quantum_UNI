<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NicaraguanRucRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ruc = strtoupper((string) $value);

        if (! preg_match('/^[0-9]{14}[A-Z0-9]?$/', $ruc)) {
            $fail('El RUC de Nicaragua no tiene un formato valido.');
        }
    }
}
