<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IniciarSesionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usuario' => ['required', 'string', 'max:255'],
            'metodo' => ['sometimes', 'in:contrasena,pin'],
            'pin' => ['exclude_unless:metodo,pin', 'required', 'string', 'regex:/^[0-9]{6}$/'],
            'contrasena' => ['exclude_if:metodo,pin', 'required', 'string', 'max:1024'],
            'recordar' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario.required' => 'Escribe tu usuario o correo.',
            'usuario.string' => 'El nombre de usuario debe ser texto.',
            'usuario.max' => 'El usuario o correo no puede superar los 255 caracteres.',
            'contrasena.required' => 'Escribe tu contraseña.',
            'contrasena.string' => 'La contraseña debe ser texto.',
            'contrasena.max' => 'La contraseña no puede superar los 1024 caracteres.',
            'pin.required' => 'Escribe tu PIN.',
            'pin.string' => 'El PIN debe ser texto.',
            'pin.regex' => 'El PIN debe contener exactamente seis dígitos.',
            'metodo.in' => 'Selecciona contraseña o PIN.',
            'recordar.boolean' => 'La opción de recordar sesión no es válida.',
        ];
    }
}
