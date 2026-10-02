<?php

namespace App\Http\Requests;

class AsignarPermisosRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return ['permisos' => ['sometimes', 'array', 'max:500'], 'permisos.*' => ['integer', 'distinct', 'exists:permisos,id']];
    }
}
