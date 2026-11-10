<?php

namespace App\Http\Requests;

use App\Rules\ContrasenaSegura;
use Illuminate\Validation\Rule;

class CredencialesPerfilRequest extends SolicitudAdministrativa
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->activo;
    }

    public function rules(): array
    {
        return [
            'operacion' => ['required', Rule::in(['contrasena', 'pin', 'retirar_pin'])],
            'contrasena_actual' => ['required', 'string', 'max:1024'],
            'contrasena' => ['exclude_unless:operacion,contrasena', 'required', 'string', 'confirmed', new ContrasenaSegura],
            'pin' => ['exclude_unless:operacion,pin', 'required', 'string', 'regex:/^[0-9]{6}$/', 'confirmed'],
        ];
    }
}
