<?php

// Validación de entrada HTTP. Base de mensajes comunes para solicitudes administrativas; la ruta y el
// servicio comprueban sus permisos explícitos.

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class SolicitudAdministrativa extends FormRequest
{
    // Admite validar esta solicitud; los permisos de la operación se comprueban por la ruta y el
    // servicio.
    public function authorize(): bool
    {
        // Cada ruta declara su permiso y el servicio lo revalida en la transacción.
        return true;
    }

    // Define mensajes legibles en español para los errores de validación; las claves corresponden a
    // campos o reglas de Laravel.
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
            'pin.confirmed' => 'Los PIN no coinciden.',
            'pin.regex' => 'El PIN debe contener exactamente seis dígitos.',
            'prohibited' => 'El campo :attribute se administra mediante su operación y permiso específicos.',
        ];
    }
}
