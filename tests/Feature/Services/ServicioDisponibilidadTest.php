<?php

use App\Models\Cabana;
use App\Models\Huesped;
use App\Models\Reservacion;
use App\Services\ServicioDisponibilidad;
use Illuminate\Validation\ValidationException;

test('la estancia usa entrada catorce salida once y conserva la zona horaria en UTC', function () {
    $periodo = app(ServicioDisponibilidad::class)->periodo('2026-10-01', '2026-10-02');
    expect($periodo['entrada']->format('Y-m-d H:i:s P'))->toBe('2026-10-01 20:00:00 +00:00')
        ->and($periodo['salida']->format('Y-m-d H:i:s P'))->toBe('2026-10-02 17:00:00 +00:00');
});

test('solo las reservaciones confirmadas y hospedadas bloquean disponibilidad', function (string $estado, bool $disponible) {
    $cabana = Cabana::factory()->create();
    Reservacion::factory()->for($cabana, 'cabana')->create(['estado' => $estado]);
    expect(app(ServicioDisponibilidad::class)->verificar($cabana, '2026-10-01', '2026-10-02', 2))->toBe($disponible);
})->with([
    'por confirmar' => ['por_confirmar', true],
    'pendiente' => ['pendiente', true],
    'confirmada' => ['confirmada', false],
    'hospedada' => ['hospedada', false],
    'finalizada' => ['finalizada', true],
    'cancelada' => ['cancelada', true],
    'no presentado' => ['no_presentado', true],
]);

test('detecta intersecciones y permite entrada el dia de otra salida', function (string $entrada, string $salida, bool $disponible) {
    $cabana = Cabana::factory()->create();
    Reservacion::factory()->for($cabana, 'cabana')->create([
        'fecha_entrada' => '2026-10-05', 'fecha_salida' => '2026-10-08',
        'entrada_prevista' => '2026-10-05 20:00:00', 'salida_prevista' => '2026-10-08 17:00:00',
    ]);
    expect(app(ServicioDisponibilidad::class)->verificar($cabana, $entrada, $salida, 2))->toBe($disponible);
})->with([
    'mismo intervalo' => ['2026-10-05', '2026-10-08', false],
    'contenido' => ['2026-10-06', '2026-10-07', false],
    'envolvente' => ['2026-10-04', '2026-10-09', false],
    'cruce al inicio' => ['2026-10-04', '2026-10-06', false],
    'cruce al final' => ['2026-10-07', '2026-10-09', false],
    'sale antes de la entrada' => ['2026-10-04', '2026-10-05', true],
    'entra despues de la salida' => ['2026-10-08', '2026-10-09', true],
]);

test('los estados manuales solo permiten nuevas reservaciones cuando estan disponibles', function (string $estado, bool $disponible) {
    $cabana = Cabana::factory()->create(['estado' => $estado]);
    expect(app(ServicioDisponibilidad::class)->verificar($cabana, '2026-10-01', '2026-10-02', 2))->toBe($disponible);
})->with([
    ['disponible', true], ['ocupada', false], ['reservada', false], ['mantenimiento', false], ['bloqueada', false],
]);

test('la disponibilidad revalida actividad capacidad y eliminacion sin confiar en el modelo previo', function () {
    $cabana = Cabana::factory()->create(['capacidad_maxima' => 4]);
    $servicio = app(ServicioDisponibilidad::class);
    expect($servicio->verificar($cabana, '2026-10-01', '2026-10-02', 4))->toBeTrue()
        ->and($servicio->verificar($cabana, '2026-10-01', '2026-10-02', 5))->toBeFalse();
    Cabana::whereKey($cabana->id)->update(['activa' => false]);
    expect($servicio->verificar($cabana, '2026-10-01', '2026-10-02', 2))->toBeFalse();
    Cabana::whereKey($cabana->id)->update(['activa' => true]);
    $cabana->delete();
    expect($servicio->verificar($cabana, '2026-10-01', '2026-10-02', 2))->toBeFalse();
});

test('la exclusion interna para reagendar no oculta otra reservacion ni otro bloqueo', function () {
    $cabana = Cabana::factory()->create();
    $reservacion = Reservacion::factory()->for($cabana, 'cabana')->create();
    $servicio = app(ServicioDisponibilidad::class);
    expect($servicio->verificar($cabana, '2026-10-01', '2026-10-02', 2, $reservacion))->toBeTrue();
    Reservacion::factory()->for($cabana, 'cabana')->create();
    expect($servicio->verificar($cabana, '2026-10-01', '2026-10-02', 2, $reservacion))->toBeFalse();
    $cabana->forceFill(['estado' => 'reservada'])->save();
    expect($servicio->verificar($cabana, '2026-11-01', '2026-11-02', 2, $reservacion))->toBeFalse();
});

test('otras cabanas no se bloquean y el mismo huesped conserva sus relaciones sin duplicarse', function () {
    $huesped = Huesped::factory()->create();
    $ocupada = Cabana::factory()->create();
    $libre = Cabana::factory()->create();
    $reservacion = Reservacion::factory()->for($ocupada, 'cabana')->for($huesped, 'huesped')->create();
    expect(app(ServicioDisponibilidad::class)->verificar($libre, '2026-10-01', '2026-10-02', 2))->toBeTrue()
        ->and($huesped->reservaciones->sole()->id)->toBe($reservacion->id)
        ->and($ocupada->reservaciones->sole()->huesped_id)->toBe($huesped->id);
    $this->assertDatabaseCount('huespedes', 1);
    $huesped->delete();
    $ocupada->delete();
    expect($reservacion->fresh()->huesped->id)->toBe($huesped->id)
        ->and($reservacion->fresh()->cabana->id)->toBe($ocupada->id);
});

test('las reservaciones eliminadas logicamente no ocupan la cabana', function () {
    $cabana = Cabana::factory()->create();
    Reservacion::factory()->for($cabana, 'cabana')->create()->delete();
    expect(app(ServicioDisponibilidad::class)->verificar($cabana, '2026-10-01', '2026-10-02', 2))->toBeTrue();
});

test('el servicio rechaza fechas invalidas aun cuando no se invoca desde HTTP', function (string $entrada, string $salida) {
    expect(fn () => app(ServicioDisponibilidad::class)->periodo($entrada, $salida))->toThrow(ValidationException::class);
})->with([
    ['2026-02-30', '2026-03-01'], ['2026-10-02', '2026-10-02'],
    ['2026-10-03', '2026-10-02'], ['01/10/2026', '2026-10-02'],
]);

test('el servicio rechaza cantidades imposibles fuera del formulario', function (int $cantidad) {
    expect(fn () => app(ServicioDisponibilidad::class)->disponibles('2026-10-01', '2026-10-02', $cantidad))
        ->toThrow(ValidationException::class);
})->with([0, -1, 65536]);
