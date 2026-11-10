<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ConsultarSesionesRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return [
            'usuario' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['activa', 'cerrada', 'vencida', 'revocada'])],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }
}
