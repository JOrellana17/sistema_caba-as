<?php

use App\Models\Cabana;
use App\Models\Huesped;
use App\Models\RegistroAuditoria;
use App\Models\Reservacion;
use App\Models\Usuario;
use Illuminate\Support\Str;

function datosAltaEnFormulario(array $cambios = []): array
{
    return array_replace(['nombre' => 'María de prueba', 'telefono' => '+504 (001) 234-5678'], $cambios);
}

test('crea un huesped sin identidad desde el formulario y reutiliza su id en reservacion', function () {
    $usuario = crearAdministradorDePrueba();
    $cabana = Cabana::factory()->create();
    $respuesta = $this->actingAs($usuario)->postJson(route('huespedes.guardar_desde_formulario'), datosAltaEnFormulario())
        ->assertCreated()->assertJsonPath('huesped.nombre', 'María de prueba')->assertJsonPath('huesped.numero_identidad', null)
        ->assertJsonPath('huesped.identidad_visible', true)->assertJsonPath('huesped.activo', true)
        ->assertJsonMissingPath('huesped.notas')->assertJsonMissingPath('huesped.identidad_busqueda')
        ->assertHeader('Cache-Control', 'no-store, private');
    $huesped = Huesped::sole();
    expect($respuesta->json('huesped.id'))->toBe($huesped->id)->and($huesped->creado_por)->toBe($usuario->id);
    $this->getJson(route('huespedes.buscar', ['termino_busqueda' => 'maria']))->assertJsonPath('resultados.0.id', $huesped->id);
    $this->post(route('reservaciones.guardar'), [
        'cabana_id' => $cabana->id, 'huesped_id' => $huesped->id,
        'fecha_entrada' => '2026-10-05', 'fecha_salida' => '2026-10-07',
        'hora_entrada' => '14:00', 'hora_salida' => '11:00',
        'cantidad_huespedes' => 3, 'moneda' => 'HNL', 'estado_inicial' => 'por_confirmar',
    ])->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseCount('huespedes', 1);
    expect(Reservacion::sole()->huesped_id)->toBe($huesped->id);
    $this->get(route('reservaciones.ver', Reservacion::sole()))->assertOk()->assertSee('Sin registrar');
    $auditoria = RegistroAuditoria::where('modulo', 'huespedes')->sole();
    expect($auditoria->accion)->toBe('creado')->and($auditoria->toJson())->not->toContain('María', '234-5678');
});

test('el alta en formulario exige nombre y telefono y rechaza identidad duplicada o campos sensibles', function () {
    $this->actingAs(crearAdministradorDePrueba());
    $this->postJson(route('huespedes.guardar_desde_formulario'), [])->assertUnprocessable()->assertJsonValidationErrors(['nombre', 'telefono'])
        ->assertJsonMissingPath('errors.numero_identidad');
    $this->postJson(route('huespedes.guardar_desde_formulario'), datosAltaEnFormulario(['telefono' => 'abc']))->assertUnprocessable()->assertJsonValidationErrors('telefono');
    $this->postJson(route('huespedes.guardar_desde_formulario'), datosAltaEnFormulario(['numero_identidad' => '---']))->assertUnprocessable()->assertJsonValidationErrors('numero_identidad');
    $this->postJson(route('huespedes.guardar_desde_formulario'), datosAltaEnFormulario(['activo' => false]))->assertUnprocessable()->assertJsonValidationErrors('activo');
    $existente = Huesped::factory()->create(['numero_identidad' => 'DOC-001']);
    $existente->delete();
    $this->postJson(route('huespedes.guardar_desde_formulario'), datosAltaEnFormulario(['numero_identidad' => 'doc 001']))->assertUnprocessable()->assertJsonValidationErrors('numero_identidad');
    $this->assertDatabaseCount('huespedes', 1);
    $this->assertDatabaseCount('registros_auditoria', 0);
});

test('el alta en formulario requiere sesion y ambos permisos sin conceder facultades', function (array $permisos) {
    $this->postJson(route('huespedes.guardar_desde_formulario'), datosAltaEnFormulario())->assertUnauthorized();
    crearAdministradorDePrueba();
    $usuario = Usuario::factory()->create();
    otorgarPermisosDePrueba($usuario, $permisos);
    $this->actingAs($usuario)->postJson(route('huespedes.guardar_desde_formulario'), datosAltaEnFormulario())->assertForbidden();
    $this->assertDatabaseCount('huespedes', 0);
    $this->assertDatabaseCount('registros_auditoria', 0);
    expect($usuario->fresh()->esAdministrador())->toBeFalse();
})->with([[[]], [['huespedes.buscar']], [['huespedes.crear']]]);

test('solo quienes crean huespedes ven el alta dentro del formulario sin salir de la reservacion', function () {
    $this->actingAs(crearAdministradorDePrueba())->get(route('reservaciones.crear'))->assertOk()
        ->assertSee('data-alta-huesped', false)->assertSee('Crear y seleccionar huésped')->assertDontSee('Crear huésped (nueva pestaña)');
});

test('sin permiso de crear conserva el buscador sin ofrecer alta en reservaciones', function () {
    crearAdministradorDePrueba();
    $usuario = Usuario::factory()->create();
    otorgarPermisosDePrueba($usuario, ['reservaciones.crear', 'huespedes.buscar']);
    $this->actingAs($usuario)->get(route('reservaciones.crear'))->assertOk()->assertSee('data-buscador-huesped', false)
        ->assertDontSee('data-alta-huesped', false)->assertDontSee('Crear y seleccionar huésped');
});

test('el alta en formulario exige CSRF real y acepta el token del encabezado', function () {
    $this->actingAs(crearAdministradorDePrueba());
    $this->app->instance('env', 'local');
    $this->postJson(route('huespedes.guardar_desde_formulario'), datosAltaEnFormulario())->assertStatus(419);
    $this->assertDatabaseCount('huespedes', 0);
    $token = Str::random(40);
    $this->withSession(['_token' => $token])->withHeader('X-CSRF-TOKEN', $token)
        ->postJson(route('huespedes.guardar_desde_formulario'), datosAltaEnFormulario())->assertCreated();
    $this->assertDatabaseCount('huespedes', 1);
});
