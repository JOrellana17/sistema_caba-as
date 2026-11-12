<?php

// Normalización compartida entre guardado y búsqueda de huéspedes; conserva los valores originales de
// contacto.

namespace App\Support;

use Illuminate\Support\Str;

class NormalizadorHuesped
{
    // Quita diferencias de tildes y mayúsculas y unifica espacios para buscar nombres sin modificar el
    // valor original.
    public static function nombre(string $valor): string
    {
        return trim(preg_replace('/[\s\p{Z}]+/u', ' ', Str::lower(Str::ascii($valor))));
    }

    // Retira separadores de formato del teléfono manteniendo el texto y sus ceros iniciales; no agrega
    // prefijos nacionales.
    public static function telefono(string $valor): string
    {
        return preg_replace('/[\s\p{Z}\p{Pd}()+.]+/u', '', $valor);
    }

    // Retira espacios y guiones y normaliza letras a mayúsculas, conservando los ceros iniciales del
    // documento.
    public static function identidad(string $valor): string
    {
        return mb_strtoupper(preg_replace('/[\s\p{Z}\p{Pd}]+/u', '', $valor), 'UTF-8');
    }

    // Cuenta letras y números Unicode para exigir un término de búsqueda significativo.
    public static function caracteresUtiles(string $valor): int
    {
        return preg_match_all('/[\p{L}\p{N}]/u', $valor);
    }

    // Escapa !, % y _ como texto literal y agrega comodines externos para una búsqueda parcial
    // parametrizada.
    public static function patron(string $valor): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $valor).'%';
    }
}
