<?php

namespace App\Http\Requests;

use App\Support\NormalizadorHuesped;
use Closure;

class ConsultarHuespedesRequest extends SolicitudAdministrativa
{
    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:120', function (string $atributo, mixed $valor, Closure $fallar) {
                if (is_string($valor) && NormalizadorHuesped::caracteresUtiles($valor) < 3) {
                    $fallar('Escribe al menos tres letras o números para buscar.');
                }
            }],
            'eliminados' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
            'lista_negra' => ['nullable', 'boolean'],
            'frecuente' => ['nullable', 'boolean'],
            'vip' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
