<?php

use App\Models\Huesped;
use App\Models\RegistroAuditoria;
use App\Models\Usuario;
use App\Services\ServicioAuditoria;
use Illuminate\Database\UniqueConstraintViolationException;

function datosHuespedDePrueba(array $cambios = []): array
{
    return array_replace([
        'nombre' => 'María José Núñez', 'telefono' => '+1 (001) 234-5678',
        'numero_identidad' => '00-AB-0123', 'nacionalidad' => 'Nacionalidad de prueba',
        'notas' => 'Nota reservada', 'notas_incidentes' => 'Incidente reservado', 'vip' => '0',
    ], $cambios);
}

test('el alta admite identidad y distintos codigos de pais', function () {
    $this->actingAs(crearAdministradorDePrueba())->post(route('huespedes.guardar'), [
        'nombre' => 'Cliente internacional', 'telefono' => '+44 020 1234 5678', 'numero_identidad' => 'GB-000123',
    ])->assertSessionHasNoErrors();
    $huesped = Huesped::sole();
    expect($huesped->telefono)->toBe('+44 020 1234 5678')->and($huesped->numero_identidad)->toBe('GB-000123')
        ->and($huesped->vip)->toBeFalse();
});

test('crea edita y muestra huesped conservando originales y auditando sin datos personales', function () {
    $administrador = crearAdministradorDePrueba();
    $this->actingAs($administrador)->post(route('huespedes.guardar'), datosHuespedDePrueba())
        ->assertSessionHasNoErrors()->assertRedirect();
    $huesped = Huesped::sole();
    expect($huesped->telefono)->toBe('+1 (001) 234-5678')->and($huesped->numero_identidad)->toBe('00-AB-0123')
        ->and($huesped->creado_por)->toBe($administrador->id)->and($huesped->activo)->toBeTrue();
    $this->put(route('huespedes.actualizar', $huesped), datosHuespedDePrueba(['nombre' => 'Nombre cambiado', 'vip' => 1]))
        ->assertRedirectToRoute('huespedes.ver', $huesped)->assertSessionHasNoErrors();
    expect($huesped->fresh()->vip)->toBeTrue();
    $auditoria = RegistroAuditoria::where('modulo', 'huespedes')->get();
    expect($auditoria)->toHaveCount(2);
    expect($auditoria->toJson())->not->toContain('Nota reservada', 'Incidente reservado', '00-AB-0123', '+1 (001)', 'María', 'Nombre cambiado');
    expect($auditoria->last()->valores_nuevos['campos_modificados'])->toContain('nombre', 'vip');
    $this->get(route('huespedes.ver', $huesped))->assertOk()->assertSee('00-AB-0123')->assertSee('Historial de cambios');
    $this->get(route('huespedes.listar'))->assertOk()->assertSee('Nombre cambiado');
    $this->get(route('huespedes.editar', $huesped))->assertOk()->assertSee('00-AB-0123');
    $this->get(route('huespedes.crear'))->assertOk()->assertSee('Nuevo huésped');
});

test('exige nombre y telefono e impide datos malformados o condiciones inyectadas', function (string $campo, mixed $valor) {
    $this->actingAs(crearAdministradorDePrueba())->post(route('huespedes.guardar'), datosHuespedDePrueba([$campo => $valor]))
        ->assertSessionHasErrors($campo);
    $this->assertDatabaseCount('huespedes', 0);
    $this->assertDatabaseCount('registros_auditoria', 0);
})->with([
    ['nombre', null], ['telefono', null],
    ['nombre', ['malformado']], ['telefono', ['malformado']], ['numero_identidad', ['malformado']],
    ['telefono', '---'], ['telefono', 'abc'], ['numero_identidad', '---'], ['numero_identidad', '<script>'],
    ['nombre', str_repeat('a', 151)], ['telefono', str_repeat('1', 81)], ['numero_identidad', str_repeat('a', 81)],
    ['activo', false], ['frecuente', true], ['lista_negra', true], ['creado_por', 123],
    ['nombre_busqueda', 'inyectado'], ['telefono_busqueda', '123'], ['identidad_busqueda', '123'],
    ['deleted_at', '2026-10-01'], ['vip', 'quizás'],
]);

test('rechaza identidad equivalente tambien eliminada y permite telefonos compartidos', function () {
    $administrador = crearAdministradorDePrueba();
    $this->actingAs($administrador)->post(route('huespedes.guardar'), datosHuespedDePrueba())->assertSessionHasNoErrors();
    $huesped = Huesped::sole();
    $this->post(route('huespedes.guardar'), datosHuespedDePrueba(['numero_identidad' => '00 ab 0123']))
        ->assertSessionHasErrors('numero_identidad');
    $this->delete(route('huespedes.eliminar', $huesped))->assertRedirect();
    $this->post(route('huespedes.guardar'), datosHuespedDePrueba(['numero_identidad' => '00AB0123']))
        ->assertSessionHasErrors('numero_identidad');
    $this->post(route('huespedes.guardar'), datosHuespedDePrueba(['numero_identidad' => '00AB9999']))->assertSessionHasNoErrors();
    $this->assertDatabaseCount('huespedes', 2);
});

test('la base tambien impide duplicados normalizados por fuera de la validacion HTTP', function () {
    Huesped::factory()->create(['numero_identidad' => '00-ABC']);
    expect(fn () => Huesped::factory()->create(['numero_identidad' => '00 abc']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('cambia condiciones solo con su permiso y sin modificar otros datos', function (string $ruta, string $permiso, string $campo) {
    crearAdministradorDePrueba();
    $operador = Usuario::factory()->create();
    otorgarPermisosDePrueba($operador, [$permiso]);
    $huesped = Huesped::factory()->create(['activo' => false]);
    $this->actingAs($operador)->put(route($ruta, $huesped), ['valor' => true, 'numero_identidad' => 'inyectada'])
        ->assertRedirectToRoute('inicio')->assertSessionHasNoErrors();
    expect($huesped->fresh()->{$campo})->toBeTrue()->and($huesped->fresh()->numero_identidad)->toBeNull();
    $this->assertDatabaseHas('registros_auditoria', ['modulo' => 'huespedes', 'accion' => $campo.'_actualizado', 'usuario_id' => $operador->id]);
    $this->put(route($ruta, $huesped), ['valor' => false])->assertSessionHasNoErrors();
    expect($huesped->fresh()->{$campo})->toBeFalse();
    $this->put(route($ruta, $huesped), ['valor' => ['no']])->assertSessionHasErrors('valor');
})->with([
    ['huespedes.estado', 'huespedes.cambiar_estado', 'activo'],
    ['huespedes.lista_negra', 'huespedes.cambiar_lista_negra', 'lista_negra'],
    ['huespedes.frecuente', 'huespedes.marcar_frecuente', 'frecuente'],
]);

test('cada ruta exige autenticacion y permiso propio', function (string $metodo, string $ruta, bool $conId) {
    $huesped = Huesped::factory()->create();
    $url = route($ruta, $conId ? $huesped : []);
    $this->{$metodo}($url)->assertRedirectToRoute('sesion.mostrar');
    $this->actingAs(Usuario::factory()->create())->{$metodo}($url)->assertForbidden();
    $this->assertDatabaseCount('registros_auditoria', 0);
})->with([
    ['get', 'huespedes.listar', false], ['get', 'huespedes.crear', false], ['post', 'huespedes.guardar', false],
    ['get', 'huespedes.ver', true], ['get', 'huespedes.editar', true], ['put', 'huespedes.actualizar', true],
    ['put', 'huespedes.estado', true], ['put', 'huespedes.lista_negra', true], ['put', 'huespedes.frecuente', true],
    ['delete', 'huespedes.eliminar', true], ['post', 'huespedes.restaurar', true],
    ['get', 'huespedes.buscar', false], ['get', 'huespedes.buscador', false],
]);

test('buscar muestra identidad con permiso de buscar sin otorgar ver crear o privilegios', function () {
    crearAdministradorDePrueba();
    $operador = Usuario::factory()->create();
    otorgarPermisosDePrueba($operador, ['huespedes.buscar']);
    $huesped = Huesped::factory()->create(['nombre' => 'María Gómez', 'numero_identidad' => '000-ABC', 'notas' => 'Privado', 'notas_incidentes' => 'Incidente', 'activo' => false, 'lista_negra' => true]);
    $this->actingAs($operador)->getJson(route('huespedes.buscar', ['termino_busqueda' => 'maria']))
        ->assertOk()->assertJsonCount(1, 'resultados')->assertJsonPath('resultados.0.id', $huesped->id)
        ->assertJsonPath('resultados.0.numero_identidad', '000-ABC')->assertJsonPath('resultados.0.identidad_visible', true)
        ->assertJsonPath('resultados.0.activo', false)->assertJsonPath('resultados.0.lista_negra', true)
        ->assertJsonMissingPath('resultados.0.notas')->assertJsonMissingPath('resultados.0.identidad_busqueda')
        ->assertDontSee('Privado')->assertDontSee('Incidente')->assertHeader('Cache-Control', 'no-store, private');
    $this->get(route('huespedes.buscador'))->assertOk()->assertSee('data-buscador-huesped', false)->assertDontSee('Crear huésped (nueva pestaña)');
    $this->get(route('huespedes.ver', $huesped))->assertForbidden();
    $this->post(route('huespedes.guardar'), datosHuespedDePrueba())->assertForbidden();
    expect($operador->fresh()->esAdministrador())->toBeFalse();
    $this->assertDatabaseCount('huespedes', 1);
    $this->assertDatabaseCount('registros_auditoria', 0);
});

test('ver permite consultar identidad pero no habilita buscar ni mutaciones', function () {
    crearAdministradorDePrueba();
    $lector = Usuario::factory()->create();
    otorgarPermisosDePrueba($lector, ['huespedes.ver']);
    $huesped = Huesped::factory()->create(['numero_identidad' => '001-ABC']);
    $this->actingAs($lector)->get(route('huespedes.ver', $huesped))->assertOk()->assertSee('001-ABC')
        ->assertDontSee('Historial de cambios')->assertDontSee('Editar ficha')->assertDontSee('Eliminar huésped');
    $this->getJson(route('huespedes.buscar', ['termino_busqueda' => 'ABC']))->assertForbidden();
    $this->get(route('huespedes.listar', ['buscar' => 'ABC']))->assertForbidden();
    $this->get(route('huespedes.listar', ['eliminados' => 1]))->assertForbidden();
});

test('consulta JSON valida termino y diferencia errores de resultados vacios', function () {
    $this->getJson(route('huespedes.buscar', ['termino_busqueda' => 'ana']))->assertUnauthorized();
    $this->actingAs(crearAdministradorDePrueba());
    foreach (['', 'ab', '  --()', '0-1', str_repeat('a', 121), ['malformado']] as $termino) {
        $this->getJson(route('huespedes.buscar', ['termino_busqueda' => $termino]))->assertUnprocessable()->assertJsonValidationErrors('termino_busqueda');
    }
    $this->getJson(route('huespedes.buscar', ['termino_busqueda' => 'nadie']))
        ->assertOk()->assertExactJson(['resultados' => [], 'hay_mas' => false]);
});

test('elimina y restaura conservando identidad condiciones e historial', function () {
    $administrador = crearAdministradorDePrueba();
    $huesped = Huesped::factory()->create(['nombre' => 'Restaurable', 'numero_identidad' => '001ABC', 'lista_negra' => true, 'vip' => true, 'activo' => false]);
    $this->actingAs($administrador)->delete(route('huespedes.eliminar', $huesped))->assertRedirectToRoute('huespedes.listar');
    $this->assertSoftDeleted($huesped);
    $this->get(route('huespedes.ver', $huesped))->assertNotFound();
    $this->get(route('huespedes.editar', $huesped))->assertNotFound();
    $this->getJson(route('huespedes.buscar', ['termino_busqueda' => 'Restaurable']))->assertJsonCount(0, 'resultados');
    $this->get(route('huespedes.listar', ['eliminados' => 1]))->assertOk()->assertSee('Restaurable');
    $this->post(route('huespedes.restaurar', $huesped))->assertRedirectToRoute('huespedes.ver', $huesped);
    expect($huesped->fresh()->numero_identidad)->toBe('001ABC')->and($huesped->fresh()->lista_negra)->toBeTrue()
        ->and($huesped->fresh()->activo)->toBeFalse()->and($huesped->fresh()->vip)->toBeTrue();
    $this->assertDatabaseCount('huespedes', 1);
    expect(RegistroAuditoria::pluck('accion')->all())->toBe(['eliminado', 'restaurado']);
});

test('filtra condiciones pagina y reutiliza normalizacion del buscador', function () {
    $administrador = crearAdministradorDePrueba();
    Huesped::factory()->count(21)->create(['nombre' => 'José cliente', 'vip' => true, 'creado_por' => $administrador->id]);
    Huesped::factory()->create(['nombre' => 'José distinto', 'vip' => false]);
    $this->actingAs($administrador)->get(route('huespedes.listar', ['buscar' => 'jose', 'vip' => 1]))
        ->assertOk()->assertViewHas('huespedes', fn ($huespedes) => $huespedes->total() === 21 && $huespedes->count() === 20);
    $this->get(route('huespedes.listar', ['buscar' => 'jose', 'vip' => 1, 'page' => 2]))->assertOk()
        ->assertViewHas('huespedes', fn ($huespedes) => $huespedes->count() === 1);
});

test('revierte una edicion si falla el registro de auditoria', function () {
    $huesped = Huesped::factory()->create(['nombre' => 'Original']);
    $this->actingAs(crearAdministradorDePrueba())->withoutExceptionHandling();
    $this->mock(ServicioAuditoria::class)->shouldReceive('registrarCambio')->once()->andThrow(new RuntimeException('Auditoría no disponible'));
    expect(fn () => $this->put(route('huespedes.actualizar', $huesped), datosHuespedDePrueba()))->toThrow(RuntimeException::class, 'Auditoría no disponible');
    expect($huesped->fresh()->nombre)->toBe('Original');
    $this->assertDatabaseCount('registros_auditoria', 0);
});

test('escapa texto de la ficha y conserva entradas ante errores', function () {
    $huesped = Huesped::factory()->create(['nombre' => '<script>alert(1)</script>', 'notas' => '</textarea><script>alert(2)</script>']);
    $this->actingAs(crearAdministradorDePrueba())->get(route('huespedes.ver', $huesped))->assertOk()
        ->assertSee($huesped->nombre)->assertDontSee($huesped->nombre, false)->assertSee($huesped->notas)->assertDontSee($huesped->notas, false);
    $this->from(route('huespedes.crear'))->post(route('huespedes.guardar'), datosHuespedDePrueba(['telefono' => null]))
        ->assertRedirectToRoute('huespedes.crear')->assertSessionHasErrors('telefono');
    $this->get(route('huespedes.crear'))->assertOk()->assertSee('María José Núñez')->assertSee('00-AB-0123')->assertSee('Nota reservada');
});

test('el alta desde el componente abre otra pestana solo con permiso y no preselecciona', function () {
    $this->actingAs(crearAdministradorDePrueba())->get(route('huespedes.buscador'))->assertOk()
        ->assertSee('Crear huésped (nueva pestaña)')->assertSee('target="_blank"', false)
        ->assertSee('name="huesped_id" value=""', false);
    $this->assertDatabaseCount('huespedes', 0);
});

test('muestra ceros como datos registrados en vez de tratarlos como campos vacios', function () {
    $huesped = Huesped::factory()->create(['telefono' => '0', 'numero_identidad' => '0']);
    $this->actingAs(crearAdministradorDePrueba())->get(route('huespedes.ver', $huesped))->assertOk()
        ->assertSee('<dd class="col-sm-8">0</dd>', false);
    $this->get(route('huespedes.listar'))->assertOk()->assertDontSee('Sin registrar');
});

test('permite varios huespedes sin identidad y eliminar la identidad al editar', function () {
    $this->actingAs(crearAdministradorDePrueba());
    foreach ([null, '', 'omitida'] as $identidad) {
        $datos = ['nombre' => 'Huésped sin documento', 'telefono' => '+504 0012345678'];
        if ($identidad !== 'omitida') {
            $datos['numero_identidad'] = $identidad;
        }
        $this->post(route('huespedes.guardar'), $datos)->assertSessionHasNoErrors();
    }
    $this->assertDatabaseCount('huespedes', 3);
    expect(Huesped::whereNull('numero_identidad')->whereNull('identidad_busqueda')->count())->toBe(3);
    $huesped = Huesped::factory()->create(['numero_identidad' => 'DOC-001']);
    $this->put(route('huespedes.actualizar', $huesped), ['nombre' => 'Huésped actualizado', 'telefono' => '+44 02012345678', 'numero_identidad' => null])
        ->assertSessionHasNoErrors();
    expect($huesped->fresh()->numero_identidad)->toBeNull()->and($huesped->fresh()->identidad_busqueda)->toBeNull();
});
