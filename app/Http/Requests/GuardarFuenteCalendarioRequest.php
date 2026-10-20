<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class GuardarFuenteCalendarioRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return [
            'cabana_id' => ['required', 'integer', 'exists:cabanas,id'],
            'nombre' => ['required', 'string', 'max:100'],
            'proveedor' => ['required', Rule::in(['airbnb', 'booking'])],
            'url' => ['nullable', 'string', 'max:4000'],
            'interpretacion_fechas' => ['required', Rule::in(['noches_estancia', 'dias_completos'])],
            'zona_horaria' => ['required', 'timezone:all'],
            'cobertura_desde' => ['nullable', 'required_with:cobertura_hasta', 'date_format:Y-m-d'],
            'cobertura_hasta' => ['nullable', 'required_with:cobertura_desde', 'date_format:Y-m-d', 'after_or_equal:cobertura_desde'],
        ];
    }
}
