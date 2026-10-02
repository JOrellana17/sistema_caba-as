<?php

namespace App\Http\Requests;

class CambiarReservacionRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return [
            'motivo_cancelacion' => ['nullable', 'string', 'max:5000'],
            'hora_entrada' => ['sometimes', 'required', 'date_format:H:i'],
            'hora_salida' => ['sometimes', 'required', 'date_format:H:i'],
            'estado' => ['prohibited'],
            'huesped_id' => ['prohibited'],
            'cabana_id' => ['prohibited'],
            'subtotal' => ['prohibited'],
            'moneda' => ['prohibited'],
        ];
    }
}
