<?php

namespace App\Http\Requests;

use App\Rules\ContrasenaSegura;

class ContrasenaUsuarioRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return ['contrasena' => ['required', 'string', 'confirmed', new ContrasenaSegura]];
    }
}
