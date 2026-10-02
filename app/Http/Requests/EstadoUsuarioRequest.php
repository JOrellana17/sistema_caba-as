<?php

namespace App\Http\Requests;

class EstadoUsuarioRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return ['activo' => ['required', 'boolean']];
    }
}
