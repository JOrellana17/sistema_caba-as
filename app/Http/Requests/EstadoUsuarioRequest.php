<?php

// Validación de entrada HTTP. Acepta el estado activo como booleano para una operación autorizada de
// activación o desactivación.

namespace App\Http\Requests;

class EstadoUsuarioRequest extends SolicitudAdministrativa
{
    // Acepta el estado activo como booleano para una operación autorizada de activación o
    // desactivación.
    public function rules(): array
    {
        return ['activo' => ['required', 'boolean']];
    }
}
