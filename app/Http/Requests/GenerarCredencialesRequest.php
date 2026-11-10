<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class GenerarCredencialesRequest extends SolicitudAdministrativa
{
    public function authorize(): bool
    {
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
