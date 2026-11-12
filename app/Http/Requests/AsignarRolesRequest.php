<?php

// Validación de entrada HTTP. Acepta identificadores de roles existentes sin duplicados; la protección
// de roles especiales se aplica en el servicio.

namespace App\Http\Requests;

class AsignarRolesRequest extends SolicitudAdministrativa
{
    // Acepta identificadores de roles existentes sin duplicados; la protección de roles especiales se
    // aplica en el servicio.
    public function rules(): array
    {
        return ['roles' => ['sometimes', 'array', 'max:100'], 'roles.*' => ['integer', 'distinct', 'exists:roles,id']];
    }
}
