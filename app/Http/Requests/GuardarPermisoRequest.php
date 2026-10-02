<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class GuardarPermisoRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:120', 'regex:/^[a-z][a-z0-9_]*\\.[a-z][a-z0-9_]*$/', Rule::unique('permisos')->ignore($this->route('permiso'))],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'protegido' => ['prohibited'],
        ];
    }
}
