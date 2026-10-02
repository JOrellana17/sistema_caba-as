<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class SolicitudAdministrativa extends FormRequest
{
    public function authorize(): bool
    {
        // Cada ruta declara su permiso y el servicio lo revalida en la transacción.
        return true;
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute supera el tamaño permitido.',
            'email' => 'El correo electrónico no es válido.',
            'unique' => 'El valor de :attribute ya está registrado.',
            'regex' => 'El formato de :attribute no es válido.',
            'boolean' => 'El valor de :attribute debe ser sí o no.',
            'array' => 'La selección de :attribute no es válida.',
            'integer' => 'El identificador seleccionado no es válido.',
            'exists' => 'Uno de los registros seleccionados ya no existe.',
            'distinct' => 'La selección contiene registros repetidos.',
            'confirmed' => 'Las contraseñas no coinciden.',
            'prohibited' => 'El campo :attribute se administra mediante su operación y permiso específicos.',
        ];
    }
}
