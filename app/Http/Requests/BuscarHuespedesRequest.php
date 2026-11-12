<?php

// Validación de entrada HTTP. Valida el término antes de consultar coincidencias; el servicio exige
// además tres letras o números útiles.

namespace App\Http\Requests;

use App\Support\NormalizadorHuesped;
use Closure;

class BuscarHuespedesRequest extends SolicitudAdministrativa
{
    // Valida el término antes de consultar coincidencias; el servicio exige además tres letras o
    // números útiles.
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

    // Asocia nombres técnicos de campos con etiquetas en español para que los mensajes del validador
    // sean comprensibles.
    public function attributes(): array
    {
        return ['termino_busqueda' => 'término de búsqueda'];
    }
}
