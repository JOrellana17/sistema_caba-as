<?php

namespace App\Http\Requests;

use App\Models\Huesped;
use App\Support\NormalizadorHuesped;
use Closure;

class GuardarHuespedRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return [
            'nombre' => ['bail', 'required', 'string', 'max:150'],
            'telefono' => ['bail', 'required', 'string', 'max:80', 'regex:/^[+0-9\s\p{Z}\p{Pd}().]+$/u', function (string $atributo, mixed $valor, Closure $fallar) {
                if (! preg_match('/[0-9]/', $valor)) {
                    $fallar('El teléfono debe contener números, con su código de país cuando corresponda.');
                }
            }],
            'numero_identidad' => ['bail', 'nullable', 'string', 'max:80', 'regex:/^[\p{L}\p{N}\s\p{Z}\p{Pd}]+$/u', function (string $atributo, mixed $valor, Closure $fallar) {
                if (NormalizadorHuesped::caracteresUtiles($valor) === 0) {
                    $fallar('La identidad debe contener letras o números.');

                    return;
                }
                $huesped = $this->route('huesped');
                if (Huesped::withTrashed()->where('identidad_busqueda', NormalizadorHuesped::identidad($valor))
                    ->when($huesped, fn ($consulta) => $consulta->whereKeyNot($huesped->id))->exists()) {
                    $fallar('La identidad ya está registrada. Busca la ficha existente o solicita su restauración.');
                }
            }],
            'nacionalidad' => ['nullable', 'string', 'max:120'],
            'notas' => ['nullable', 'string', 'max:5000'],
            'notas_incidentes' => ['nullable', 'string', 'max:5000'],
            'vip' => ['sometimes', 'boolean'],
            'activo' => ['prohibited'],
            'frecuente' => ['prohibited'],
            'lista_negra' => ['prohibited'],
            'creado_por' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'nombre_busqueda' => ['prohibited'],
            'telefono_busqueda' => ['prohibited'],
            'identidad_busqueda' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return ['telefono' => 'teléfono', 'numero_identidad' => 'número de identidad', 'notas_incidentes' => 'notas de incidentes', 'vip' => 'VIP'];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'telefono.regex' => 'Introduce el teléfono con números y separadores de formato; puedes incluir el código internacional.',
            'numero_identidad.regex' => 'Introduce la identidad con letras, números, espacios o guiones.',
        ]);
    }
}
