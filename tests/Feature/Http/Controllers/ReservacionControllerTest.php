<?php

use App\Models\Cabana;
use App\Models\Huesped;
use App\Models\RegistroAuditoria;
use App\Models\Reservacion;
use App\Models\Usuario;
use App\Services\ServicioAuditoria;
use App\Services\ServicioReservaciones;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

function datosReservacionDePrueba(Cabana $cabana, Huesped $huesped, array $cambios = []): array
{
    return array_replace([
        'cabana_id' => $cabana->id, 'huesped_id' => $huesped->id,
        'fecha_entrada' => '2026-10-01', 'fecha_salida' => '2026-10-03',
        'hora_entrada' => '14:00', 'hora_salida' => '11:00',
        'cantidad_huespedes' => 3, 'moneda' => 'HNL', 'estado_inicial' => 'por_confirmar',
        'notas' => 'Nota privada de reservación',
    ], $cambios);
}

test('crea solicitudes no bloqueantes reutilizando el huesped con tarifas historicas y auditoria', function (string $estado) {
    $usuario = crearAdministradorDePrueba();
    $cabana = Cabana::factory()->create();
    $huesped = Huesped::factory()->create(['activo' => false, 'lista_negra' => true]);
    $datos = datosReservacionDePrueba($cabana, $huesped, ['estado_inicial' => $estado]);
    $this->actingAs($usuario)->post(route('reservaciones.guardar'), $datos)->assertRedirect()->assertSessionHasNoErrors();
    $registro = Reservacion::sole();
    expect($registro->huesped_id)->toBe($huesped->id)->and($registro->estado)->toBe($estado)
        ->and($registro->subtotal)->toBe('271.50')->and($registro->precio_base)->toBe('123.45')
        ->and($registro->personas_adicionales)->toBe(1)->and($registro->noches)->toBe(2)
        ->and($registro->entrada_prevista->format('H:i'))->toBe('20:00');
    $this->post(route('reservaciones.guardar'), $datos)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('huespedes', 1);
    $this->assertDatabaseCount('reservaciones', 2);
    $registroAuditoria = RegistroAuditoria::where('modulo', 'reservaciones')->first();
    expect($registroAuditoria->valores_nuevos['huesped_id'])->toBe($huesped->id)
        ->and($registroAuditoria->toJson())->not->toContain('Nota privada', $huesped->numero_identidad);
    $this->get(route('reservaciones.listar'))->assertOk()->assertSee('271.50');
    $this->get(route('reservaciones.ver', $registro))->assertOk()->assertSee('no bloquea disponibilidad')
        ->assertSee($huesped->numero_identidad)->assertSee('Lista negra')->assertSee('Inactivo');
    $this->get(route('reservaciones.editar', $registro))->assertOk()->assertSee($huesped->nombre);
})->with(['por_confirmar', 'pendiente']);

test('la segunda confirmacion revalida el intervalo y la cancelacion libera la cabana', function () {
    $usuario = crearAdministradorDePrueba();
    $cabana = Cabana::factory()->create();
    $huesped = Huesped::factory()->create();
    $this->actingAs($usuario)->post(route('reservaciones.guardar'), datosReservacionDePrueba($cabana, $huesped))->assertSessionHasNoErrors();
    $this->post(route('reservaciones.guardar'), datosReservacionDePrueba($cabana, $huesped))->assertSessionHasNoErrors();
    [$primera, $segunda] = Reservacion::orderBy('id')->get()->all();
    $this->put(route('reservaciones.confirmar', $primera))->assertSessionHasNoErrors();
    $this->put(route('reservaciones.confirmar', $segunda))->assertSessionHasErrors('cabana_id');
    expect($segunda->fresh()->estado)->toBe('por_confirmar');
    $this->put(route('reservaciones.cancelar', $primera), ['motivo_cancelacion' => 'Cliente avisó fuera del sistema'])->assertSessionHasNoErrors();
    expect($primera->fresh()->estado)->toBe('cancelada')->and($primera->fresh()->cancelado_por)->toBe($usuario->id);
    $this->put(route('reservaciones.confirmar', $segunda))->assertSessionHasNoErrors();
    expect($segunda->fresh()->estado)->toBe('confirmada');
    $this->assertDatabaseCount('huespedes', 1);
});

test('usa tarifas independientes en dolares sin conversion y exige configurarlas', function () {
    $cabana = Cabana::factory()->create(['precio_base_usd' => '5.10', 'precio_persona_adicional_usd' => '0.20']);
    $huesped = Huesped::factory()->create();
    $this->actingAs(crearAdministradorDePrueba())->post(route('reservaciones.guardar'), datosReservacionDePrueba($cabana, $huesped, ['moneda' => 'USD']))
        ->assertSessionHasNoErrors();
    expect(Reservacion::sole()->subtotal)->toBe('10.60')->and(Reservacion::sole()->moneda)->toBe('USD');
    $sinDolares = Cabana::factory()->create();
    $this->post(route('reservaciones.guardar'), datosReservacionDePrueba($sinDolares, $huesped, ['moneda' => 'USD']))
        ->assertSessionHasErrors('moneda');
    $this->assertDatabaseCount('reservaciones', 1);
});

test('reagenda conservando tarifa original o aplicando tarifa actual y protege el cambio de moneda', function () {
    $cabana = Cabana::factory()->create(['precio_base_usd' => '5.10', 'precio_persona_adicional_usd' => '0.20']);
    $huesped = Huesped::factory()->create();
    $usuario = crearAdministradorDePrueba();
    $this->actingAs($usuario)->post(route('reservaciones.guardar'), datosReservacionDePrueba($cabana, $huesped))->assertSessionHasNoErrors();
    $registro = Reservacion::sole();
    $codigo = $registro->codigo;
    $cabana->fill(['precio_base' => '200.00', 'precio_persona_adicional' => '30.00', 'capacidad_base' => 3])->save();
    $datos = datosReservacionDePrueba($cabana, $huesped, ['fecha_salida' => '2026-10-04', 'tarifa' => 'conservar']);
    unset($datos['estado_inicial']);
    $this->put(route('reservaciones.actualizar', $registro), $datos)->assertSessionHasNoErrors();
    expect($registro->fresh()->subtotal)->toBe('407.25')->and($registro->fresh()->capacidad_base_aplicada)->toBe(2);
    $this->put(route('reservaciones.actualizar', $registro), array_replace($datos, ['tarifa' => 'actualizar']))->assertSessionHasNoErrors();
    expect($registro->fresh()->subtotal)->toBe('600.00')->and($registro->fresh()->capacidad_base_aplicada)->toBe(3);
    $this->put(route('reservaciones.actualizar', $registro), array_replace($datos, ['moneda' => 'USD']))->assertSessionHasErrors('tarifa');
    expect($registro->fresh()->moneda)->toBe('HNL');
    $this->put(route('reservaciones.actualizar', $registro), array_replace($datos, ['moneda' => 'USD', 'tarifa' => 'actualizar']))->assertSessionHasNoErrors();
    expect($registro->fresh()->subtotal)->toBe('15.30')->and($registro->fresh()->codigo)->toBe($codigo)
        ->and($registro->fresh()->creado_por)->toBe($usuario->id);
});

test('cambiar de cabana o huesped mantiene la identidad de la reservacion y no duplica clientes', function () {
    $cabana = Cabana::factory()->create();
    $otra = Cabana::factory()->create(['precio_base' => '200.00']);
    $huesped = Huesped::factory()->create();
    $otro = Huesped::factory()->create();
    $registro = Reservacion::factory()->for($cabana, 'cabana')->for($huesped, 'huesped')->create(['estado' => 'pendiente']);
    $datos = datosReservacionDePrueba($otra, $otro, ['tarifa' => 'conservar']);
    unset($datos['estado_inicial']);
    $this->actingAs(crearAdministradorDePrueba())->put(route('reservaciones.actualizar', $registro), $datos)->assertSessionHasNoErrors();
    expect($registro->fresh()->cabana_id)->toBe($otra->id)->and($registro->fresh()->huesped_id)->toBe($otro->id)
        ->and($registro->fresh()->precio_base)->toBe('123.45')->and($registro->fresh()->estado)->toBe('pendiente');
    $this->assertDatabaseCount('huespedes', 2);
    $this->assertDatabaseCount('reservaciones', 1);
});

test('conserva todos los campos y el huesped seleccionado al volver de un error', function () {
    $cabana = Cabana::factory()->create();
    $huesped = Huesped::factory()->create(['nombre' => 'Huésped elegido']);
    $this->actingAs(crearAdministradorDePrueba())->from(route('reservaciones.crear'))
        ->post(route('reservaciones.guardar'), datosReservacionDePrueba($cabana, $huesped, ['cantidad_huespedes' => 0]))
        ->assertRedirectToRoute('reservaciones.crear')->assertSessionHasErrors('cantidad_huespedes');
    $this->get(route('reservaciones.crear'))->assertOk()->assertSee('Huésped elegido')->assertSee('Nota privada de reservación')
        ->assertSee('value="2026-10-03"', false)->assertSee('name="huesped_id" value="'.$huesped->id.'"', false);
    $this->assertDatabaseCount('huespedes', 1);
    $this->assertDatabaseCount('reservaciones', 0);
});

test('rechaza campos protegidos seleccion eliminada y datos invalidos sin guardar', function (array $cambio, string $campo) {
    $cabana = Cabana::factory()->create();
    $huesped = Huesped::factory()->create();
    $this->actingAs(crearAdministradorDePrueba())->post(route('reservaciones.guardar'), datosReservacionDePrueba($cabana, $huesped, $cambio))
        ->assertSessionHasErrors($campo);
    $this->assertDatabaseCount('reservaciones', 0);
    $this->assertDatabaseCount('registros_auditoria', 0);
})->with([
    [['huesped_id' => 999999], 'huesped_id'],
    [['cabana_id' => 999999], 'cabana_id'],
    [['fecha_entrada' => ['2026-10-01']], 'fecha_entrada'],
    [['fecha_entrada' => '2026-02-30'], 'fecha_entrada'],
    [['fecha_salida' => '2026-10-01'], 'fecha_salida'],
    [['hora_entrada' => '25:00'], 'hora_entrada'],
    [['cantidad_huespedes' => 5], 'cabana_id'],
    [['moneda' => 'EUR'], 'moneda'],
    [['subtotal' => '0.01'], 'subtotal'],
    [['estado' => 'confirmada'], 'estado'],
    [['estado_inicial' => 'confirmada'], 'estado_inicial'],
    [['precio_base' => '0'], 'precio_base'],
    [['creado_por' => 1], 'creado_por'],
    [['entrada_prevista' => '2026-10-01 00:00'], 'entrada_prevista'],
]);

test('revalida un huesped eliminado incluso al confirmar y una cabana desactivada', function () {
    $cabana = Cabana::factory()->create();
    $huesped = Huesped::factory()->create();
    $registro = Reservacion::factory()->for($cabana, 'cabana')->for($huesped, 'huesped')->create(['estado' => 'pendiente']);
    $usuario = crearAdministradorDePrueba();
    $huesped->delete();
    $this->actingAs($usuario)->put(route('reservaciones.confirmar', $registro))->assertSessionHasErrors('huesped_id');
    $huesped->restore();
    $cabana->forceFill(['activa' => false])->save();
    $this->put(route('reservaciones.confirmar', $registro))->assertSessionHasErrors('cabana_id');
    expect($registro->fresh()->estado)->toBe('pendiente');
});

test('entrada y salida siguen el flujo y la reprogramacion queda cerrada al hospedarse', function () {
    $registro = Reservacion::factory()->create();
    $usuario = crearAdministradorDePrueba();
    $this->travelTo(CarbonImmutable::parse('2026-10-01 20:00:00', 'UTC'));
    $this->actingAs($usuario)->put(route('reservaciones.entrada', $registro))->assertSessionHasNoErrors();
    expect($registro->fresh()->estado)->toBe('hospedada')->and($registro->fresh()->fecha_entrada_registrada->format('H:i'))->toBe('20:00');
    $datos = datosReservacionDePrueba($registro->cabana, $registro->huesped, ['tarifa' => 'conservar']);
    unset($datos['estado_inicial']);
    $this->put(route('reservaciones.actualizar', $registro), $datos)->assertSessionHasErrors('estado');
    $this->put(route('reservaciones.cancelar', $registro))->assertSessionHasErrors('estado');
    $this->travelTo(CarbonImmutable::parse('2026-10-02 17:00:00', 'UTC'));
    $this->put(route('reservaciones.salida', $registro))->assertSessionHasNoErrors();
    expect($registro->fresh()->estado)->toBe('finalizada');
    $this->put(route('reservaciones.entrada', $registro))->assertSessionHasErrors('estado');
});

test('no presentado espera hasta la salida prevista mientras cancelar libera antes', function () {
    $registro = Reservacion::factory()->create();
    $usuario = crearAdministradorDePrueba();
    $this->travelTo(CarbonImmutable::parse('2026-10-02 16:59:59', 'UTC'));
    $this->actingAs($usuario)->put(route('reservaciones.no_presentado', $registro))->assertSessionHasErrors('estado');
    $this->travelTo(CarbonImmutable::parse('2026-10-02 17:00:00', 'UTC'));
    $this->put(route('reservaciones.no_presentado', $registro))->assertSessionHasNoErrors();
    expect($registro->fresh()->estado)->toBe('no_presentado')->and($registro->fresh()->fecha_no_presentado)->not->toBeNull();
    $otra = Reservacion::factory()->create();
    $this->put(route('reservaciones.cancelar', $otra))->assertSessionHasNoErrors();
    expect($otra->fresh()->estado)->toBe('cancelada');
});

test('la administracion autoriza entrada temprana y salida tardia con control de solapamiento', function () {
    $registro = Reservacion::factory()->create();
    $usuario = crearAdministradorDePrueba();
    $operador = Usuario::factory()->create();
    otorgarPermisosDePrueba($operador, ['reservaciones.editar', 'reservaciones.registrar_entrada', 'reservaciones.registrar_salida']);
    $this->travelTo(CarbonImmutable::parse('2026-10-01 18:00:00', 'UTC'));
    $this->actingAs($operador)->put(route('reservaciones.entrada', $registro))->assertSessionHasErrors('estado');
    $this->put(route('reservaciones.horarios', $registro), ['hora_entrada' => '12:00'])->assertForbidden();
    $this->flushSession();
    $this->actingAs($usuario)->put(route('reservaciones.horarios', $registro), ['hora_entrada' => '12:00', 'hora_salida' => '13:00'])->assertSessionHasNoErrors();
    expect($registro->fresh()->entrada_prevista->format('Y-m-d H:i:s'))->toBe('2026-10-01 18:00:00');
    expect(CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'))->toBe('2026-10-01 18:00:00');
    $this->flushSession();
    $this->actingAs($operador)->put(route('reservaciones.entrada', $registro))->assertRedirectToRoute('inicio')->assertSessionHasNoErrors();
    Reservacion::factory()->for($registro->cabana, 'cabana')->create([
        'fecha_entrada' => '2026-10-02', 'fecha_salida' => '2026-10-03',
        'entrada_prevista' => '2026-10-02 20:00:00', 'salida_prevista' => '2026-10-03 17:00:00',
    ]);
    $this->flushSession();
    $this->actingAs($usuario)->put(route('reservaciones.horarios', $registro), ['hora_salida' => '15:00'])->assertSessionHasErrors('cabana_id');
    expect($registro->fresh()->salida_prevista->format('H:i'))->toBe('19:00');
    $this->travelTo(CarbonImmutable::parse('2026-10-02 19:01:00', 'UTC'));
    $this->flushSession();
    $this->actingAs($operador)->put(route('reservaciones.salida', $registro))->assertForbidden();
    $this->flushSession();
    $this->actingAs($usuario)->put(route('reservaciones.salida', $registro))->assertSessionHasNoErrors();
});

test('toda operacion de reservacion exige autenticacion y su permiso propio', function (string $metodo, string $ruta, bool $identificador) {
    $registro = Reservacion::factory()->create();
    $url = route($ruta, $identificador ? $registro : []);
    $this->{$metodo}($url)->assertRedirectToRoute('sesion.mostrar');
    $this->actingAs(Usuario::factory()->create())->{$metodo}($url)->assertForbidden();
    $this->assertDatabaseCount('registros_auditoria', 0);
})->with([
    ['get', 'reservaciones.listar', false], ['get', 'reservaciones.crear', false],
    ['post', 'reservaciones.guardar', false], ['get', 'reservaciones.ver', true],
    ['get', 'reservaciones.editar', true], ['put', 'reservaciones.actualizar', true],
    ['put', 'reservaciones.confirmar', true], ['put', 'reservaciones.cancelar', true],
    ['put', 'reservaciones.entrada', true], ['put', 'reservaciones.salida', true],
    ['put', 'reservaciones.pendiente', true], ['put', 'reservaciones.no_presentado', true],
    ['put', 'reservaciones.horarios', true], ['delete', 'reservaciones.eliminar', true],
]);

test('un permiso de crear no sustituye buscar y ver reservas no expone identidad sin permiso de huespedes', function () {
    crearAdministradorDePrueba();
    $operador = Usuario::factory()->create();
    otorgarPermisosDePrueba($operador, ['reservaciones.crear', 'reservaciones.ver']);
    $registro = Reservacion::factory()->create();
    $this->actingAs($operador)->get(route('reservaciones.crear'))->assertForbidden();
    $this->post(route('reservaciones.guardar'), datosReservacionDePrueba($registro->cabana, $registro->huesped))->assertForbidden();
    $this->get(route('reservaciones.ver', $registro))->assertOk()->assertDontSee($registro->huesped->numero_identidad);
    expect($operador->fresh()->can('huespedes.buscar'))->toBeFalse();
    $this->assertDatabaseCount('reservaciones', 1);
});

test('la eliminacion logica conserva relaciones y no permite borrar una estancia confirmada', function () {
    $usuario = crearAdministradorDePrueba();
    $registro = Reservacion::factory()->create();
    $this->actingAs($usuario)->delete(route('reservaciones.eliminar', $registro))->assertSessionHasErrors('estado');
    $this->put(route('reservaciones.cancelar', $registro))->assertSessionHasNoErrors();
    $this->delete(route('reservaciones.eliminar', $registro))->assertSessionHasNoErrors();
    $this->assertSoftDeleted('reservaciones', ['id' => $registro->id]);
    $this->assertDatabaseCount('huespedes', 1);
    $this->assertDatabaseCount('cabanas', 1);
    $this->get(route('reservaciones.ver', $registro))->assertNotFound();
});

test('un fallo de auditoria revierte toda la reservacion y su importe', function () {
    $usuario = crearAdministradorDePrueba();
    $cabana = Cabana::factory()->create();
    $huesped = Huesped::factory()->create();
    $this->mock(ServicioAuditoria::class)->shouldReceive('registrarCambio')->once()->andThrow(new RuntimeException('Auditoría de prueba'));
    $solicitud = Request::create('/reservaciones', 'POST');
    $solicitud->setUserResolver(fn () => $usuario);
    expect(fn () => app(ServicioReservaciones::class)->guardar($solicitud, datosReservacionDePrueba($cabana, $huesped)))
        ->toThrow(RuntimeException::class, 'Auditoría de prueba');
    $this->assertDatabaseCount('reservaciones', 0);
    $this->assertDatabaseCount('registros_auditoria', 0);
});

test('el servicio rechaza una cabaña cambiada por otro operador antes de guardar', function () {
    $registro = Reservacion::factory()->create(['estado' => 'pendiente']);
    $otra = Cabana::factory()->create();
    Reservacion::whereKey($registro->id)->update(['cabana_id' => $otra->id]);
    $usuario = crearAdministradorDePrueba();
    $solicitud = Request::create('/reservaciones', 'PUT');
    $solicitud->setUserResolver(fn () => $usuario);
    expect(fn () => app(ServicioReservaciones::class)->guardar($solicitud, array_diff_key(datosReservacionDePrueba($otra, $registro->huesped, ['tarifa' => 'actualizar']), ['estado_inicial' => true]), $registro))
        ->toThrow(ValidationException::class);
    expect($registro->fresh()->cabana_id)->toBe($otra->id);
    $this->assertDatabaseCount('registros_auditoria', 0);
});

test('protege la capacidad y eliminacion de cabanas con reservaciones vigentes', function () {
    $registro = Reservacion::factory()->create(['cantidad_huespedes' => 4]);
    $this->actingAs(crearAdministradorDePrueba())->delete(route('cabanas.eliminar', $registro->cabana))->assertSessionHasErrors('cabana');
    $datos = ['nombre' => 'Cabaña de prueba', 'capacidad_base' => 2, 'capacidad_maxima' => 3, 'precio_base' => '123.45', 'precio_persona_adicional' => '12.30'];
    unset($datos['estado'], $datos['activa']);
    $this->put(route('cabanas.actualizar', $registro->cabana), $datos)->assertSessionHasErrors('capacidad_maxima');
    expect($registro->cabana->fresh()->capacidad_maxima)->toBe(4)->and($registro->cabana->fresh()->trashed())->toBeFalse();
});

test('tarifas USD se guardan exactas en el catalogo y no se admite una pareja incompleta', function () {
    $usuario = crearAdministradorDePrueba();
    $this->actingAs($usuario)->post(route('cabanas.guardar'), ['nombre' => 'USD de prueba', 'capacidad_base' => 2, 'capacidad_maxima' => 3, 'precio_base' => '123.45', 'precio_persona_adicional' => '12.30', 'estado' => 'disponible', 'activa' => true, 'precio_base_usd' => '10.10', 'precio_persona_adicional_usd' => '0.20'])->assertSessionHasNoErrors();
    expect(Cabana::sole()->precio_base_usd)->toBe('10.10')->and(Cabana::sole()->precio_persona_adicional_usd)->toBe('0.20');
    $this->post(route('cabanas.guardar'), ['nombre' => 'USD de prueba', 'capacidad_base' => 2, 'capacidad_maxima' => 3, 'precio_base' => '123.45', 'precio_persona_adicional' => '12.30', 'estado' => 'disponible', 'activa' => true, 'precio_base_usd' => '10.10'])->assertSessionHasErrors('precio_persona_adicional_usd');
    $this->assertDatabaseCount('cabanas', 1);
});

test('escapa notas del registro y protege los filtros malformados', function () {
    $registro = Reservacion::factory()->create(['notas' => '<script>alert(1)</script>']);
    $this->actingAs(crearAdministradorDePrueba())->get(route('reservaciones.ver', $registro))
        ->assertOk()->assertSee($registro->notas)->assertDontSee($registro->notas, false);
    $this->get(route('reservaciones.listar', ['desde' => ['2026-10-01'], 'hasta' => '2026-10-02']))
        ->assertSessionHasErrors('desde');
});

test('una cabana con una estancia sin salida registrada no admite otra entrada fisica', function () {
    $cabana = Cabana::factory()->create();
    $ocupante = Reservacion::factory()->for($cabana, 'cabana')->create(['estado' => 'hospedada']);
    $siguiente = Reservacion::factory()->for($cabana, 'cabana')->create([
        'fecha_entrada' => '2026-10-02', 'fecha_salida' => '2026-10-03',
        'entrada_prevista' => '2026-10-02 20:00:00', 'salida_prevista' => '2026-10-03 17:00:00',
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-10-02 20:00:00', 'UTC'));
    $this->actingAs(crearAdministradorDePrueba())->put(route('reservaciones.entrada', $siguiente))->assertSessionHasErrors('cabana_id');
    $this->put(route('reservaciones.salida', $ocupante))->assertSessionHasNoErrors();
    $this->put(route('reservaciones.entrada', $siguiente))->assertSessionHasNoErrors();
    expect($siguiente->fresh()->estado)->toBe('hospedada');
});

test('la ficha muestra solo las reservaciones del huesped y exige permiso de consulta', function () {
    $registro = Reservacion::factory()->create();
    $otra = Reservacion::factory()->create();
    $usuario = crearAdministradorDePrueba();
    $this->actingAs($usuario)->get(route('huespedes.ver', $registro->huesped))->assertOk()
        ->assertSee($registro->codigo)->assertDontSee($otra->codigo);
    $operador = Usuario::factory()->create();
    otorgarPermisosDePrueba($operador, ['huespedes.ver']);
    $this->flushSession();
    $this->actingAs($operador)->get(route('huespedes.ver', $registro->huesped))->assertOk()
        ->assertDontSee($registro->codigo)->assertDontSee('Reservaciones y estancias');
});

test('un identificador de cabana malformado vuelve al formulario sin romper la seleccion', function () {
    $cabana = Cabana::factory()->create();
    $huesped = Huesped::factory()->create();
    $this->actingAs(crearAdministradorDePrueba())->from(route('reservaciones.crear'))->post(route('reservaciones.guardar'),
        datosReservacionDePrueba($cabana, $huesped, ['cabana_id' => [$cabana->id]]))->assertSessionHasErrors('cabana_id');
    $this->get(route('reservaciones.crear'))->assertOk()->assertSee($huesped->nombre);
});

test('conserva centavos exactos en MySQL para importes grandes dentro del limite', function () {
    $cabana = Cabana::factory()->create(['precio_base' => '9999999999.99', 'precio_persona_adicional' => '0.00']);
    $huesped = Huesped::factory()->create();
    $this->actingAs(crearAdministradorDePrueba())->post(route('reservaciones.guardar'), datosReservacionDePrueba($cabana, $huesped, [
        'cantidad_huespedes' => 2, 'fecha_salida' => CarbonImmutable::parse('2026-10-01')->addDays(9999)->format('Y-m-d'),
    ]))->assertSessionHasNoErrors();
    expect(Reservacion::sole()->subtotal)->toBe('99989999999900.01');
});
