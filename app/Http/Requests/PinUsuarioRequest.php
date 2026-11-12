<?php

// Validación de entrada HTTP. Exige seis dígitos y confirmación para establecer un PIN; excluye esa
// entrada cuando se solicita retirarlo.

namespace App\Http\Requests;

class PinUsuarioRequest extends SolicitudAdministrativa
{
    // Exige seis dígitos y confirmación para establecer un PIN; excluye esa entrada cuando se solicita
    // retirarlo.
    public function rules(): array
    {
        return [
            'retirar_pin' => ['sometimes', 'boolean'],
            'pin' => ['exclude_if:retirar_pin,1', 'required', 'string', 'regex:/^[0-9]{6}$/', 'confirmed'],
        ];
    }
}
