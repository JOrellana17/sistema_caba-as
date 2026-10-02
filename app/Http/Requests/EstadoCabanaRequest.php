<?php

namespace App\Http\Requests;

use App\Models\Cabana;
use Illuminate\Validation\Rule;

class EstadoCabanaRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(array_keys(Cabana::ESTADOS))],
            'activa' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ['in' => 'Selecciona un estado operativo permitido.']);
    }
}
