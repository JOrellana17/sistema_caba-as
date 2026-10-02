<?php

namespace App\Http\Requests;

use App\Rules\ContrasenaSegura;
use Illuminate\Validation\Rule;

class GuardarUsuarioRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        $usuario = $this->route('usuario');

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'usuario' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9._-]+$/', Rule::unique('usuarios')->ignore($usuario)],
            'correo' => ['nullable', 'email', 'max:255', Rule::unique('usuarios')->ignore($usuario)],
            'contrasena' => $usuario ? ['prohibited'] : ['required', 'string', 'confirmed', new ContrasenaSegura],
            'activo' => ['prohibited'],
            'roles' => ['prohibited'],
            'permisos' => ['prohibited'],
        ];
    }
}
