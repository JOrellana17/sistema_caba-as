<?php

// Validación de entrada HTTP. Permite motivo y horas en una transición; prohíbe enviar directamente
// estado, cliente, cabaña o importes para evadir el flujo.

namespace App\Http\Requests;

class CambiarReservacionRequest extends SolicitudAdministrativa
{
    // Permite motivo y horas en una transición; prohíbe enviar directamente estado, cliente, cabaña o
    // importes para evadir el flujo.
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
