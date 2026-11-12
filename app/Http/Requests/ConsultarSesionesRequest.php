<?php

// Validación de entrada HTTP. Valida usuario, estado y fechas del historial; el controlador limita
// siempre la vista personal a la cuenta autenticada.

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ConsultarSesionesRequest extends SolicitudAdministrativa
{
    // Valida usuario, estado y fechas del historial; el controlador limita siempre la vista personal a
    // la cuenta autenticada.
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
