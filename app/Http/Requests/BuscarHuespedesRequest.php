<?php

namespace App\Http\Requests;

use App\Support\NormalizadorHuesped;
use Closure;

class BuscarHuespedesRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return [
            'termino_busqueda' => ['bail', 'required', 'string', 'max:120', function (string $atributo, mixed $valor, Closure $fallar) {
                if (NormalizadorHuesped::caracteresUtiles($valor) < 3) {
                    $fallar('Escribe al menos tres letras o números para buscar.');
                }
            }],
        ];
    }

    public function attributes(): array
    {
        return ['termino_busqueda' => 'término de búsqueda'];
    }
}
