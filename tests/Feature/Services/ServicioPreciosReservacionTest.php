<?php

use App\Models\Cabana;
use App\Models\Reservacion;
use App\Services\ServicioPreciosReservacion;
use Illuminate\Validation\ValidationException;

test('los importes por noche usan decimales exactos y adicionales solo sobre capacidad base', function (int $cantidad, string $esperado, int $adicionales) {
    $cabana = Cabana::factory()->make(['precio_base' => '0.10', 'precio_persona_adicional' => '0.20']);
    $resultado = app(ServicioPreciosReservacion::class)->calcular($cabana, [
        'fecha_entrada' => '2026-10-01', 'fecha_salida' => '2026-10-04', 'cantidad_huespedes' => $cantidad, 'moneda' => 'HNL',
    ]);
    expect($resultado['subtotal'])->toBe($esperado)->and($resultado['personas_adicionales'])->toBe($adicionales)->and($resultado['noches'])->toBe(3);
})->with([[1, '0.30', 0], [2, '0.30', 0], [3, '0.90', 1], [4, '1.50', 2]]);

test('mantener tarifa conserva ceros explicitos aunque cambie el catalogo', function () {
    $anterior = Reservacion::factory()->make(['precio_base' => '0.00', 'precio_persona_adicional' => '0.00']);
    $resultado = app(ServicioPreciosReservacion::class)->calcular(Cabana::factory()->make(), [
        'fecha_entrada' => '2026-10-01', 'fecha_salida' => '2026-10-04', 'cantidad_huespedes' => 4, 'moneda' => 'HNL', 'tarifa' => 'conservar',
    ], $anterior);
    expect($resultado['subtotal'])->toBe('0.00')->and($resultado['precio_base'])->toBe('0.00');
});

test('rechaza el desbordamiento del importe sin redondear ni truncar', function () {
    $cabana = Cabana::factory()->make(['precio_base' => '9999999999.99', 'precio_persona_adicional' => '9999999999.99']);
    expect(fn () => app(ServicioPreciosReservacion::class)->calcular($cabana, [
        'fecha_entrada' => '2026-10-01', 'fecha_salida' => '2027-10-01', 'cantidad_huespedes' => 65535, 'moneda' => 'HNL',
    ]))->toThrow(ValidationException::class);
});
