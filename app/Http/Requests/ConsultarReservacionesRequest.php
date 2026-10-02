<?php

namespace App\Http\Requests;

use App\Models\Reservacion;
use Illuminate\Validation\Rule;

class ConsultarReservacionesRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return [
            'codigo' => ['nullable', 'string', 'max:36'],
            'cabana_id' => ['nullable', 'integer', 'exists:cabanas,id'],
            'huesped_id' => ['nullable', 'integer', 'exists:huespedes,id'],
            'estado' => ['nullable', Rule::in(array_keys(Reservacion::ESTADOS))],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', ...($this->filled('desde') && is_string($this->input('desde')) ? ['after_or_equal:desde'] : [])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'date_format' => 'Introduce una fecha válida.',
            'after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'in' => 'El estado no es válido.',
        ]);
    }
}
