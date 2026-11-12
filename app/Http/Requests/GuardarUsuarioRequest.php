<?php

// Validación de entrada HTTP. Valida nombre, usuario único y correo opcional único. Solo el alta
// acepta contraseña y PIN; roles y estado se administran con permisos propios.

namespace App\Http\Requests;

use App\Rules\ContrasenaSegura;
use Illuminate\Validation\Rule;

class GuardarUsuarioRequest extends SolicitudAdministrativa
{
    // Valida nombre, usuario único y correo opcional único. Solo el alta acepta contraseña y PIN;
    // roles y estado se administran con permisos propios.
    public function rules(): array
    {
        $usuario = $this->route('usuario');

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'usuario' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9._-]+$/', Rule::unique('usuarios')->ignore($usuario)],
            'correo' => ['nullable', 'email', 'max:255', Rule::unique('usuarios')->ignore($usuario)],
            // La confirmación y la regla de longitud se aplican en el servidor aunque el navegador también valide.
            'contrasena' => $usuario ? ['prohibited'] : ['required', 'string', 'confirmed', new ContrasenaSegura],
            'pin' => $usuario ? ['prohibited'] : ['nullable', 'string', 'regex:/^[0-9]{6}$/', 'confirmed'],
            'activo' => ['prohibited'],
            'roles' => ['prohibited'],
            'permisos' => ['prohibited'],
        ];
    }
}
