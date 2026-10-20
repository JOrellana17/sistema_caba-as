<?php

namespace App\Http\Requests;

use App\Models\Cabana;
use Illuminate\Validation\Rule;

class EstadoCabanaRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return [
            'bloqueo_inicio' => ['nullable', Rule::requiredIf(fn () => is_string($this->input('estado')) && $this->input('estado') !== 'disponible'), 'date_format:Y-m-d\TH:i', 'required_with:bloqueo_fin'],
            'bloqueo_fin' => ['nullable', Rule::requiredIf(fn () => is_string($this->input('estado')) && $this->input('estado') !== 'disponible'), 'date_format:Y-m-d\TH:i', 'required_with:bloqueo_inicio', 'after:bloqueo_inicio'],
            'estado' => ['required', Rule::in(array_keys(Cabana::ESTADOS))],
            'activa' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ['in' => 'Selecciona un estado operativo permitido.']);
    }
}
