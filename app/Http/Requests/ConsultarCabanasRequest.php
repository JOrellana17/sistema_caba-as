<?php

namespace App\Http\Requests;

use App\Models\Cabana;
use Illuminate\Validation\Rule;

class ConsultarCabanasRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(array_keys(Cabana::ESTADOS))],
            'activa' => ['nullable', 'boolean'],
            'eliminadas' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'in' => 'El filtro de estado no es válido.',
            'min' => 'La página no es válida.',
        ]);
    }
}
