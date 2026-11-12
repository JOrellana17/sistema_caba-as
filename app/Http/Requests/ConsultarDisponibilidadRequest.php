<?php

// Validación de entrada HTTP. Exige fechas y cantidad juntas, salida posterior y ocupación positiva;
// prohíbe excluir reservaciones mediante parámetros públicos.

namespace App\Http\Requests;

class ConsultarDisponibilidadRequest extends SolicitudAdministrativa
{
    // Exige fechas y cantidad juntas, salida posterior y ocupación positiva; prohíbe excluir
    // reservaciones mediante parámetros públicos.
    public function rules(): array
    {
        return [
            'fecha_entrada' => ['required_with:fecha_salida,cantidad_huespedes', 'date_format:Y-m-d'],
            'fecha_salida' => ['required_with:fecha_entrada,cantidad_huespedes', 'date_format:Y-m-d', ...(is_string($this->input('fecha_entrada')) ? ['after:fecha_entrada'] : [])],
            'cantidad_huespedes' => ['required_with:fecha_entrada,fecha_salida', 'integer', 'min:1', 'max:65535'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'reservacion_excluida' => ['prohibited'],
        ];
    }

    // Define mensajes legibles en español para los errores de validación; las claves corresponden a
    // campos o reglas de Laravel.
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'date_format' => 'Introduce una fecha válida.',
            'after' => 'La salida debe ser posterior a la entrada.',
            'min' => 'La cantidad de huéspedes debe ser al menos uno.',
            'required_with' => 'Completa la entrada, la salida y la cantidad de huéspedes.',
        ]);
    }
}
