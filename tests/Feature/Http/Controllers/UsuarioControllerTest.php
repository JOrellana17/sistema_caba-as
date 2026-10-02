<?php

use App\Models\Permiso;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

test('crea y edita una cuenta sin otorgar roles ni privilegios', function () {
    $administrador = crearAdministradorDePrueba();
    $contrasena = Str::random(24);

    $this->actingAs($administrador)->post(route('usuarios.guardar'), [
        'nombre' => 'Ana', 'usuario' => 'ana', 'correo' => null,
        'contrasena' => $contrasena, 'contrasena_confirmation' => $contrasena,
    ])->assertRedirectToRoute('usuarios.listar');

    $usuario = Usuario::where('usuario', 'ana')->sole();
    expect(Hash::check($contrasena, $usuario->contrasena))->toBeTrue();
    expect($usuario->roles)->toHaveCount(0);
    expect($usuario->permisos)->toHaveCount(0);
    $this->assertDatabaseHas('registros_auditoria', ['usuario_id' => $administrador->id, 'registro_id' => $usuario->id, 'accion' => 'creado']);

    $this->put(route('usuarios.actualizar', $usuario), ['nombre' => 'Ana Actualizada', 'usuario' => 'ana', 'correo' => 'ana@example.test'])
        ->assertRedirectToRoute('usuarios.listar');
    $this->assertDatabaseHas('usuarios', ['id' => $usuario->id, 'nombre' => 'Ana Actualizada']);
    expect(RegistroAuditoria::get()->toJson())->not->toContain($contrasena, $usuario->contrasena, 'ana@example.test');
});

test('rechaza inyectar privilegios en la edicion ordinaria', function (string $campo, mixed $valor) {
    $administrador = crearAdministradorDePrueba();
    $usuario = Usuario::factory()->create();
    $nombre = $usuario->nombre;

    $this->actingAs($administrador)->put(route('usuarios.actualizar', $usuario), [
        'nombre' => 'Modificado', 'usuario' => $usuario->usuario, 'correo' => null, $campo => $valor,
    ])->assertSessionHasErrors($campo);

    expect($usuario->fresh()->nombre)->toBe($nombre);
    $this->assertDatabaseCount('registros_auditoria', 0);
})->with([
    'estado' => ['activo', false],
    'roles' => ['roles', [1]],
    'permisos' => ['permisos', [1]],
    'contrasena' => ['contrasena', 'NoCambiarEstaClave'],
]);

test('protege al ultimo administrador ante desactivacion eliminacion y retiro de rol', function (string $operacion) {
    $administrador = crearAdministradorDePrueba();

    $respuesta = match ($operacion) {
        'estado' => $this->actingAs($administrador)->put(route('usuarios.estado', $administrador), ['activo' => false]),
        'roles' => $this->actingAs($administrador)->put(route('usuarios.roles', $administrador), ['roles' => []]),
        'eliminar' => $this->actingAs($administrador)->delete(route('usuarios.eliminar', $administrador)),
    };
    $respuesta->assertSessionHasErrors(['administrador' => 'Debe permanecer al menos un administrador activo.']);

    expect($administrador->fresh()->esAdministrador())->toBeTrue();
    $this->assertDatabaseCount('registros_auditoria', 0);
})->with(['estado', 'roles', 'eliminar']);

test('permite administrar otra cuenta sin perder el administrador activo', function () {
    $administrador = crearAdministradorDePrueba();
    $usuario = Usuario::factory()->create();

    $this->actingAs($administrador)->put(route('usuarios.estado', $usuario), ['activo' => false])->assertRedirect();
    $this->assertDatabaseHas('usuarios', ['id' => $usuario->id, 'activo' => false]);
    $this->put(route('usuarios.estado', $usuario), ['activo' => true])->assertRedirect();
    $this->delete(route('usuarios.eliminar', $usuario))->assertRedirect();

    $this->assertSoftDeleted('usuarios', ['id' => $usuario->id]);
    $this->assertDatabaseHas('registros_auditoria', ['registro_id' => $usuario->id, 'accion' => 'eliminado']);
    $this->get(route('usuarios.ver', $usuario))->assertNotFound();
});

test('asigna y retira roles y permisos directos con auditoria', function () {
    $administrador = crearAdministradorDePrueba();
    $usuario = Usuario::factory()->create();
    $rol = Rol::create(['nombre' => 'Consulta', 'codigo' => 'consulta']);
    $permiso = Permiso::where('codigo', 'usuarios.ver')->sole();

    $this->actingAs($administrador)->put(route('roles.permisos', $rol), ['permisos' => [$permiso->id]])->assertRedirect();
    $this->put(route('usuarios.roles', $usuario), ['roles' => [$rol->id]])->assertRedirect();
    expect($usuario->fresh()->can('usuarios.ver'))->toBeTrue();
    $this->put(route('usuarios.permisos', $usuario), ['permisos' => [$permiso->id]])->assertRedirect();
    $this->put(route('usuarios.roles', $usuario), ['roles' => []])->assertRedirect();
    expect($usuario->fresh()->can('usuarios.ver'))->toBeTrue();
    $this->put(route('usuarios.permisos', $usuario), ['permisos' => []])->assertRedirect();

    expect($usuario->fresh()->can('usuarios.ver'))->toBeFalse();
    $this->assertDatabaseCount('registros_auditoria', 5);
});

test('rechaza selecciones inexistentes y repetidas sin modificar asignaciones', function (array $roles) {
    $administrador = crearAdministradorDePrueba();
    $usuario = Usuario::factory()->create();

    $this->actingAs($administrador)->put(route('usuarios.roles', $usuario), ['roles' => $roles])->assertSessionHasErrors();

    expect($usuario->fresh()->roles)->toHaveCount(0);
    $this->assertDatabaseCount('registros_auditoria', 0);
})->with([[[99999]], [[1, 1]]]);

test('restablece la contrasena revoca el recuerdo y no registra sus valores', function () {
    $administrador = crearAdministradorDePrueba();
    $usuario = Usuario::factory()->create();
    $tokenAnterior = $usuario->remember_token;
    $contrasena = Str::random(24);

    $this->actingAs($administrador)->put(route('usuarios.contrasena', $usuario), [
        'contrasena' => $contrasena, 'contrasena_confirmation' => $contrasena,
    ])->assertRedirect();

    expect(Hash::check($contrasena, $usuario->fresh()->contrasena))->toBeTrue();
    expect($usuario->fresh()->remember_token)->not->toBe($tokenAnterior);
    expect(RegistroAuditoria::sole()->toJson())->not->toContain($contrasena, $usuario->fresh()->contrasena, $tokenAnterior);
    $this->assertDatabaseHas('registros_auditoria', ['accion' => 'contrasena_restablecida', 'registro_id' => $usuario->id]);
});

test('no reduce la longitud general de contrasenas ni guarda entradas secretas fallidas', function () {
    $administrador = crearAdministradorDePrueba();
    $usuario = Usuario::factory()->create();
    $anterior = $usuario->contrasena;

    $this->actingAs($administrador)->put(route('usuarios.contrasena', $usuario), [
        'contrasena' => 'corta', 'contrasena_confirmation' => 'corta',
    ])->assertSessionHasErrors('contrasena')
        ->assertSessionMissingInput('contrasena')->assertSessionMissingInput('contrasena_confirmation');

    expect($usuario->fresh()->contrasena)->toBe($anterior);
    $this->assertDatabaseCount('registros_auditoria', 0);
});

test('rechaza usuarios y correos duplicados', function () {
    $administrador = crearAdministradorDePrueba();
    $usuario = Usuario::factory()->create();

    $this->actingAs($administrador)->put(route('usuarios.actualizar', $usuario), [
        'nombre' => 'Duplicado', 'usuario' => $administrador->usuario, 'correo' => $administrador->correo,
    ])->assertSessionHasErrors(['usuario', 'correo']);

    expect($usuario->fresh()->usuario)->toBe($usuario->usuario);
});

test('la nueva contrasena conserva espacios y coincide con su confirmacion', function () {
    $administrador = crearAdministradorDePrueba();
    $usuario = Usuario::factory()->create();
    $contrasena = ' '.Str::random(24).' ';

    $this->actingAs($administrador)->put(route('usuarios.contrasena', $usuario), [
        'contrasena' => $contrasena, 'contrasena_confirmation' => $contrasena,
    ])->assertRedirect();

    expect(Hash::check($contrasena, $usuario->fresh()->contrasena))->toBeTrue();
});

test('una operacion administrativa sin CSRF no modifica datos', function () {
    $administrador = crearAdministradorDePrueba();
    $usuario = Usuario::factory()->create();
    $this->app->instance('env', 'local');

    $this->actingAs($administrador)->put(route('usuarios.estado', $usuario), ['activo' => false])->assertStatus(419);

    expect($usuario->fresh()->activo)->toBeTrue();
    $this->assertDatabaseCount('registros_auditoria', 0);
});
