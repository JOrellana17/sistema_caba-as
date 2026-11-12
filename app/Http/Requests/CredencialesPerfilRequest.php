<?php

// Validación de entrada HTTP. Exige la contraseña actual y la confirmación de la credencial elegida;
// excluye los campos ajenos a la operación solicitada.

namespace App\Http\Requests;

use App\Rules\ContrasenaSegura;
use Illuminate\Validation\Rule;

class CredencialesPerfilRequest extends SolicitudAdministrativa
{
    // Permite gestionar las credenciales propias solo a una cuenta activa.
    public function authorize(): bool
    {
        return (bool) $this->user()?->activo;
    }

    // Exige la contraseña actual y la confirmación de la credencial elegida; excluye los campos ajenos
    // a la operación solicitada.
    public function rules(): array
    {
        return [
            'operacion' => ['required', Rule::in(['contrasena', 'pin', 'retirar_pin'])],
            'contrasena_actual' => ['required', 'string', 'max:1024'],
            // La confirmación y la regla de longitud se aplican en el servidor aunque el navegador también valide.
            'contrasena' => ['exclude_unless:operacion,contrasena', 'required', 'string', 'confirmed', new ContrasenaSegura],
            'pin' => ['exclude_unless:operacion,pin', 'required', 'string', 'regex:/^[0-9]{6}$/', 'confirmed'],
        ];
    }
}
