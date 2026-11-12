<?php

// Validación de entrada HTTP. Restringe el tipo de credencial al contexto autorizado y exige nombre
// únicamente para sugerir un usuario.

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class GenerarCredencialesRequest extends SolicitudAdministrativa
{
    // Autoriza el contexto de generación con el permiso correspondiente, releyendo la cuenta para no
    // usar privilegios obsoletos.
    public function authorize(): bool
    {
        // Consulta los privilegios actuales del actor antes de ejecutar una modificación.
        $actor = $this->user()?->fresh();
        if (! $actor?->activo) {
            return false;
        }

        return match ($this->input('contexto')) {
            'crear' => $actor->can('usuarios.crear'),
            'contrasena' => $actor->can('usuarios.restablecer_contrasena'),
            'pin' => $actor->can('usuarios.restablecer_pin'),
            'perfil' => true,
            default => false,
        };
    }

    // Restringe el tipo de credencial al contexto autorizado y exige nombre únicamente para sugerir un
    // usuario.
    public function rules(): array
    {
        return [
            'contexto' => ['required', Rule::in(['crear', 'contrasena', 'pin', 'perfil'])],
            'tipo' => ['required', Rule::in(match ($this->input('contexto')) {
                'crear' => ['usuario', 'contrasena', 'pin'],
                'perfil' => ['contrasena', 'pin'],
                'contrasena' => ['contrasena'],
                default => ['pin'],
            })],
            'nombre' => ['exclude_unless:tipo,usuario', 'required', 'string', 'max:255'],
        ];
    }
}
