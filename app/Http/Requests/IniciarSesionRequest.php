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
            'usuario' => ['required', 'string', 'max:100'],
            'contrasena' => ['required', 'string', 'max:1024'],
            'recordar' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario.required' => 'Escribe tu nombre de usuario.',
            'usuario.string' => 'El nombre de usuario debe ser texto.',
            'usuario.max' => 'El nombre de usuario no puede superar los 100 caracteres.',
            'contrasena.required' => 'Escribe tu contraseña.',
            'contrasena.string' => 'La contraseña debe ser texto.',
            'contrasena.max' => 'La contraseña no puede superar los 1024 caracteres.',
            'recordar.boolean' => 'La opción de recordar sesión no es válida.',
        ];
    }
}
