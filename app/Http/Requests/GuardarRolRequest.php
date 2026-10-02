<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class GuardarRolRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'codigo' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles')->ignore($this->route('rol'))],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'permisos' => ['prohibited'],
        ];
    }
}
