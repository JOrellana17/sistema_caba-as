<?php

namespace App\Services;

use App\Models\Cabana;
use App\Models\Reservacion;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ServicioPreciosReservacion
{
    public function calcular(Cabana $cabana, array $datos, ?Reservacion $anterior = null): array
    {
        $moneda = $datos['moneda'];
        if (! in_array($moneda, ['HNL', 'USD'], true)) {
            throw ValidationException::withMessages(['moneda' => 'Selecciona lempiras o dólares.']);
        }
        $conservar = $anterior && $datos['tarifa'] === 'conservar';
        if ($conservar && $moneda !== $anterior->moneda) {
            throw ValidationException::withMessages(['tarifa' => 'Para cambiar de moneda selecciona la tarifa actual de esa moneda. No se realiza conversión.']);
        }
        $sufijo = $moneda === 'USD' ? '_usd' : '';
        $base = $conservar ? $anterior->precio_base : $cabana->getAttribute('precio_base'.$sufijo);
        $adicional = $conservar ? $anterior->precio_persona_adicional : $cabana->getAttribute('precio_persona_adicional'.$sufijo);
        if ($base === null || $adicional === null) {
            throw ValidationException::withMessages(['moneda' => 'La cabaña no tiene ambas tarifas configuradas en la moneda seleccionada.']);
        }
        $capacidad = $conservar ? $anterior->capacidad_base_aplicada : $cabana->capacidad_base;
        $noches = (int) CarbonImmutable::parse($datos['fecha_entrada'], 'UTC')->diffInDays(CarbonImmutable::parse($datos['fecha_salida'], 'UTC'));
        $personasAdicionales = max(0, (int) $datos['cantidad_huespedes'] - $capacidad);
        $subtotal = BigDecimal::of($base)->plus(BigDecimal::of($adicional)->multipliedBy($personasAdicionales))->multipliedBy($noches)->toScale(2);
        if ($noches < 1 || $subtotal->compareTo('99999999999999.99') > 0) {
            throw ValidationException::withMessages(['fecha_salida' => 'El importe o la duración excede la capacidad de almacenamiento.']);
        }

        return [
            'moneda' => $moneda, 'capacidad_base_aplicada' => $capacidad,
            'noches' => $noches, 'personas_adicionales' => $personasAdicionales,
            'precio_base' => (string) BigDecimal::of($base)->toScale(2),
            'precio_persona_adicional' => (string) BigDecimal::of($adicional)->toScale(2),
            'subtotal' => (string) $subtotal,
        ];
    }
}
