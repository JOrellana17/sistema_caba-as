<?php

// Validación de entrada HTTP. Valida mes, cabaña, origen y estado para construir la vista mensual del
// calendario.

namespace App\Http\Requests;

use App\Models\Reservacion;
use Illuminate\Validation\Rule;

class ConsultarCalendarioRequest extends SolicitudAdministrativa
{
    // Valida mes, cabaña, origen y estado para construir la vista mensual del calendario.
    public function rules(): array
    {
        return [
            'mes' => ['nullable', 'date_format:Y-m'],
            'cabana_id' => ['nullable', 'integer', 'exists:cabanas,id'],
            'origen' => ['nullable', Rule::in(['cec', 'airbnb', 'booking'])],
            'estado' => ['nullable', Rule::in(array_keys(Reservacion::ESTADOS))],
        ];
    }
}
