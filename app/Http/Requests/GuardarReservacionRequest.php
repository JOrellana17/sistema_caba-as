<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class GuardarReservacionRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        $editando = $this->route('reservacion') !== null;

        return [
            'evento_id' => $editando ? ['prohibited'] : ['nullable', 'integer', 'exists:eventos_calendario,id'],
            'cabana_id' => ['required', 'integer', Rule::exists('cabanas', 'id')->whereNull('deleted_at')],
            'huesped_id' => ['required', 'integer', Rule::exists('huespedes', 'id')->whereNull('deleted_at')],
            'fecha_entrada' => ['required', 'date_format:Y-m-d'],
            'fecha_salida' => ['required', 'date_format:Y-m-d', ...(is_string($this->input('fecha_entrada')) ? ['after:fecha_entrada'] : [])],
            'hora_entrada' => ['required', 'date_format:H:i'],
            'hora_salida' => ['required', 'date_format:H:i'],
            'cantidad_huespedes' => ['required', 'integer', 'min:1', 'max:65535'],
            'moneda' => ['required', Rule::in(['HNL', 'USD'])],
            'tarifa' => $editando ? ['required', Rule::in(['conservar', 'actualizar'])] : ['prohibited'],
            'estado_inicial' => $editando ? ['prohibited'] : ['sometimes', 'required', Rule::in(['pendiente'])],
            'notas' => ['nullable', 'string', 'max:5000'],
            ...array_fill_keys([
                'estado', 'codigo', 'creado_por', 'cancelado_por', 'fecha_confirmacion', 'fecha_entrada_registrada',
                'fecha_salida_registrada', 'fecha_cancelacion', 'fecha_no_presentado', 'motivo_cancelacion',
                'precio_base', 'precio_persona_adicional', 'subtotal', 'noches', 'capacidad_base_aplicada',
                'personas_adicionales', 'deleted_at', 'entrada_prevista', 'salida_prevista',
            ], ['prohibited']),
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'date_format' => 'Introduce una fecha u hora válida.',
            'after' => 'La salida debe ser posterior a la entrada.',
            'in' => 'Selecciona una opción permitida.',
        ]);
    }
}
