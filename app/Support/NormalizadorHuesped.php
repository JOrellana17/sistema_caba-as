<?php

namespace App\Support;

use Illuminate\Support\Str;

class NormalizadorHuesped
{
    public static function nombre(string $valor): string
    {
        return trim(preg_replace('/[\s\p{Z}]+/u', ' ', Str::lower(Str::ascii($valor))));
    }

    public static function telefono(string $valor): string
    {
        return preg_replace('/[\s\p{Z}\p{Pd}()+.]+/u', '', $valor);
    }

    public static function identidad(string $valor): string
    {
        return mb_strtoupper(preg_replace('/[\s\p{Z}\p{Pd}]+/u', '', $valor), 'UTF-8');
    }

    public static function caracteresUtiles(string $valor): int
    {
        return preg_match_all('/[\p{L}\p{N}]/u', $valor);
    }

    public static function patron(string $valor): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $valor).'%';
    }
}
