<?php

namespace App\Http\Requests;

class PinUsuarioRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return [
            'retirar_pin' => ['sometimes', 'boolean'],
            'pin' => ['exclude_if:retirar_pin,1', 'required', 'string', 'regex:/^[0-9]{6}$/', 'confirmed'],
        ];
    }
}
