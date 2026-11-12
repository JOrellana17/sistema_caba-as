<?php

// Validación de entrada HTTP. Valida estado, activación y período de bloqueo; los datos de tarifa e
// imagen pertenecen a otras operaciones.

namespace App\Http\Requests;

use App\Models\Cabana;
use Illuminate\Validation\Rule;

class EstadoCabanaRequest extends SolicitudAdministrativa
{
    // Valida estado, activación y período de bloqueo; los datos de tarifa e imagen pertenecen a otras
    // operaciones.
    public function rules(): array
    {
        return [
            'bloqueo_inicio' => ['nullable', Rule::requiredIf(fn () => is_string($this->input('estado')) && $this->input('estado') !== 'disponible'), 'date_format:Y-m-d\TH:i', 'required_with:bloqueo_fin'],
            'bloqueo_fin' => ['nullable', Rule::requiredIf(fn () => is_string($this->input('estado')) && $this->input('estado') !== 'disponible'), 'date_format:Y-m-d\TH:i', 'required_with:bloqueo_inicio', 'after:bloqueo_inicio'],
            'estado' => ['required', Rule::in(array_keys(Cabana::ESTADOS))],
            'activa' => ['required', 'boolean'],
        ];
    }

    // Define mensajes legibles en español para los errores de validación; las claves corresponden a
    // campos o reglas de Laravel.
    public function messages(): array
    {
        return array_merge(parent::messages(), ['in' => 'Selecciona un estado operativo permitido.']);
    }
}
