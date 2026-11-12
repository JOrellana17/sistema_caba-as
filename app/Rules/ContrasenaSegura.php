<?php

// Regla reutilizable de longitud de contraseña que se invoca desde los validadores.

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ContrasenaSegura implements ValidationRule
{
    // Exige al menos doce caracteres y hasta setenta y dos bytes, límite compatible con bcrypt;
    // comunica el error al validador de Laravel.
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value) < 12 || strlen($value) > 72) {
            $fail('La contraseña debe tener al menos 12 caracteres y no superar los 72 bytes.');
        }
    }
}
