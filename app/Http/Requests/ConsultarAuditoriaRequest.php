<?php

namespace App\Http\Requests;

class ConsultarAuditoriaRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return [
            'modulo' => ['nullable', 'string', 'max:80'],
            'accion' => ['nullable', 'string', 'max:80'],
            'usuario_id' => ['nullable', 'integer', 'exists:usuarios,id'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', ...($this->filled('desde') ? ['after_or_equal:desde'] : [])],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'date_format' => 'Introduce una fecha válida.',
            'after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ]);
    }
}
