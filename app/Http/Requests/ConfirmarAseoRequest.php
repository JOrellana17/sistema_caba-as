<?php

// Validación de entrada HTTP. Prohíbe que el navegador envíe relaciones, fechas o autor de
// confirmación: esos valores se obtienen del registro y la sesión.

namespace App\Http\Requests;

class ConfirmarAseoRequest extends SolicitudAdministrativa
{
    // Exige los permisos de consulta y confirmación de aseo antes de validar la petición.
    public function authorize(): bool
    {
        return $this->user()?->can('aseo.ver') && $this->user()->can('aseo.confirmar');
    }

    // Prohíbe que el navegador envíe relaciones, fechas o autor de confirmación: esos valores se
    // obtienen del registro y la sesión.
    public function rules(): array
    {
        return array_fill_keys(['cabana_id', 'reservacion_id', 'iniciado_en', 'confirmado_en', 'confirmado_por'], ['prohibited']);
    }
}
