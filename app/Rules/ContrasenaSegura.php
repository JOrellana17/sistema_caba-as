<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ContrasenaSegura implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value) < 12 || strlen($value) > 72) {
            $fail('La contraseña debe tener al menos 12 caracteres y no superar los 72 bytes.');
        }
    }
}
