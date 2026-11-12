<?php

// Validación de entrada HTTP. Exige una contraseña confirmada que cumpla ContrasenaSegura para el
// restablecimiento administrativo.

namespace App\Http\Requests;

use App\Rules\ContrasenaSegura;

class ContrasenaUsuarioRequest extends SolicitudAdministrativa
{
    // Exige una contraseña confirmada que cumpla ContrasenaSegura para el restablecimiento
    // administrativo.
    public function rules(): array
    {
        return ['contrasena' => ['required', 'string', 'confirmed', new ContrasenaSegura]];
    }
}
