<?php

// Validación de entrada HTTP. Acepta una lista de permisos existentes sin duplicados; el servicio
// comprueba además cuáles puede conceder el operador.

namespace App\Http\Requests;

class AsignarPermisosRequest extends SolicitudAdministrativa
{
    // Acepta una lista de permisos existentes sin duplicados; el servicio comprueba además cuáles
    // puede conceder el operador.
    public function rules(): array
    {
        return ['permisos' => ['sometimes', 'array', 'max:500'], 'permisos.*' => ['integer', 'distinct', 'exists:permisos,id']];
    }
}
