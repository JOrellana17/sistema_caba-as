<?php

use App\Models\Cabana;
use App\Models\Reservacion;
use App\Models\Usuario;

function consultaDisponibilidadDePrueba(array $cambios = []): array
{
    return array_replace(['fecha_entrada' => '2026-10-01', 'fecha_salida' => '2026-10-02', 'cantidad_huespedes' => 2], $cambios);
}

test('la consulta requiere autenticacion y el permiso propio de cada entrada', function (string $ruta) {
    $this->get(route($ruta))->assertRedirectToRoute('sesion.mostrar');
    $this->actingAs(Usuario::factory()->create())->get(route($ruta))->assertForbidden();
})->with(['reservaciones.disponibilidad', 'cabanas.disponibilidad']);

test('cada permiso de disponibilidad permite consultar sin conceder permisos de catalogo', function (string $ruta, string $permiso, string $otraRuta) {
    crearAdministradorDePrueba();
    $operador = Usuario::factory()->create();
    otorgarPermisosDePrueba($operador, [$permiso]);
    $cabana = Cabana::factory()->create(['nombre' => 'Cabaña disponible', 'notas' => 'Nota privada', 'descripcion' => 'Descripción privada']);
    $this->actingAs($operador)->get(route($ruta))->assertOk()->assertViewHas('cabanas', null);
    $this->get(route($ruta, consultaDisponibilidadDePrueba()))->assertOk()->assertSee('Cabaña disponible')
        ->assertSee('14:00')->assertSee('11:00')->assertDontSee('Nota privada')->assertDontSee('Descripción privada')
        ->assertDontSee('123.45')->assertViewHas('cabanas', fn ($resultado) => $resultado->sole()->id === $cabana->id);
    $this->get(route($otraRuta))->assertForbidden();
    $this->get(route('cabanas.ver', $cabana))->assertForbidden();
    $this->assertDatabaseCount('registros_auditoria', 0);
    $this->assertDatabaseCount('reservaciones', 0);
})->with([
    ['reservaciones.disponibilidad', 'reservaciones.ver_disponibilidad', 'cabanas.disponibilidad'],
    ['cabanas.disponibilidad', 'cabanas.ver_disponibilidad', 'reservaciones.disponibilidad'],
]);

test('la consulta informa ausencia de disponibilidad sin mostrar datos de clientes', function () {
    $usuario = crearAdministradorDePrueba();
    $cabana = Cabana::factory()->create();
    $reservacion = Reservacion::factory()->for($cabana, 'cabana')->create();
    $this->actingAs($usuario)->get(route('reservaciones.disponibilidad', consultaDisponibilidadDePrueba()))
        ->assertOk()->assertSee('No hay cabañas disponibles')
        ->assertDontSee($reservacion->huesped->nombre)->assertDontSee($reservacion->codigo);
    $this->assertDatabaseCount('reservaciones', 1);
    $this->assertDatabaseCount('huespedes', 1);
});

test('rechaza consultas incompletas o manipuladas con errores recuperables', function (array $cambio, string $campo) {
    $this->actingAs(crearAdministradorDePrueba())
        ->get(route('reservaciones.disponibilidad', consultaDisponibilidadDePrueba($cambio)))
        ->assertSessionHasErrors($campo);
    $this->assertDatabaseCount('reservaciones', 0);
})->with([
    [['fecha_entrada' => '2026-02-30'], 'fecha_entrada'],
    [['fecha_salida' => '2026-10-01'], 'fecha_salida'],
    [['fecha_salida' => '2026-09-30'], 'fecha_salida'],
    [['fecha_entrada' => ['2026-10-01']], 'fecha_entrada'],
    [['cantidad_huespedes' => 0], 'cantidad_huespedes'],
    [['cantidad_huespedes' => 1.5], 'cantidad_huespedes'],
    [['cantidad_huespedes' => 65536], 'cantidad_huespedes'],
    [['cantidad_huespedes' => ''], 'cantidad_huespedes'],
    [['fecha_salida' => ''], 'fecha_salida'],
    [['reservacion_excluida' => 123], 'reservacion_excluida'],
    [['page' => -1], 'page'],
]);

test('la consulta pagina resultados y escapa los nombres del catalogo', function () {
    $usuario = crearAdministradorDePrueba();
    Cabana::factory()->count(21)->create(['nombre' => '<script>alert(1)</script>', 'creado_por' => $usuario->id]);
    $this->actingAs($usuario)->get(route('reservaciones.disponibilidad', consultaDisponibilidadDePrueba()))
        ->assertOk()->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
        ->assertViewHas('cabanas', fn ($resultado) => $resultado->total() === 21 && $resultado->count() === 20);
    $this->get(route('reservaciones.disponibilidad', consultaDisponibilidadDePrueba(['page' => 2])))
        ->assertOk()->assertViewHas('cabanas', fn ($resultado) => $resultado->count() === 1);
});
