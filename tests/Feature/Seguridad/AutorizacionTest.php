<?php

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Gate;

test('administrador obtiene cada permiso explicito y una cuenta sin asignaciones no lo obtiene', function () {
    $administrador = crearAdministradorDePrueba();
    $usuario = Usuario::factory()->create();

    foreach (config('permisos') as $modulo => $acciones) {
        foreach ($acciones as $accion) {
            $codigo = $modulo.'.'.$accion;
            $this->assertTrue(Gate::forUser($administrador)->allows($codigo), $codigo);
            $this->assertFalse(Gate::forUser($usuario)->allows($codigo), $codigo);
        }
    }
    expect($administrador->can('huespedes.buscar'))->toBeTrue();
    expect($administrador->can('accion.inexistente'))->toBeFalse();
});

test('combina permisos por rol y directos y refleja su revocacion sin cache persistente', function () {
    crearAdministradorDePrueba();
    $usuario = Usuario::factory()->create();
    $rol = Rol::create(['nombre' => 'Consulta', 'codigo' => 'consulta']);
    $permiso = Permiso::where('codigo', 'usuarios.ver')->sole();
    $rol->permisos()->attach($permiso);
    $usuario->roles()->attach($rol);
    otorgarPermisosDePrueba($usuario, ['usuarios.ver', 'roles.ver']);

    expect($usuario->can('usuarios.ver'))->toBeTrue();
    $usuario->permisos()->detach($permiso);
    expect($usuario->can('usuarios.ver'))->toBeTrue();
    $rol->permisos()->detach();
    expect($usuario->can('usuarios.ver'))->toBeFalse();
    expect($usuario->can('roles.ver'))->toBeTrue();
});

test('una cuenta inactiva o eliminada no conserva facultades administrativas', function () {
    $administrador = crearAdministradorDePrueba();
    $administrador->activo = false;
    $administrador->save();
    expect($administrador->can('usuarios.ver'))->toBeFalse();

    $administrador->activo = true;
    $administrador->delete();
    expect($administrador->can('usuarios.ver'))->toBeFalse();
});

test('las pantallas administrativas requieren autenticacion', function (string $ruta) {
    $this->get($ruta)->assertRedirectToRoute('sesion.mostrar');
})->with(['/usuarios', '/roles', '/permisos', '/auditoria']);

test('los permisos de lectura no autorizan operaciones sensibles', function (string $metodo, string $ruta, array $datos) {
    $administrador = crearAdministradorDePrueba();
    $lector = Usuario::factory()->create();
    otorgarPermisosDePrueba($lector, ['usuarios.ver', 'roles.ver', 'permisos.ver', 'auditoria.ver']);
    $rol = Rol::where('codigo', 'administrador')->sole();
    $permiso = Permiso::where('codigo', 'usuarios.ver')->sole();
    $ruta = str_replace(['{usuario}', '{rol}', '{permiso}'], [$administrador->id, $rol->id, $permiso->id], $ruta);

    $this->actingAs($lector)->call($metodo, $ruta, $datos)->assertForbidden();

    $this->assertDatabaseCount('usuarios', 2);
    $this->assertDatabaseCount('registros_auditoria', 0);
    expect($administrador->fresh()->esAdministrador())->toBeTrue();
})->with([
    'crear usuario' => ['POST', '/usuarios', []],
    'editar usuario' => ['PUT', '/usuarios/{usuario}', []],
    'estado usuario' => ['PUT', '/usuarios/{usuario}/estado', ['activo' => 0]],
    'contrasena usuario' => ['PUT', '/usuarios/{usuario}/contrasena', []],
    'roles usuario' => ['PUT', '/usuarios/{usuario}/roles', []],
    'permisos usuario' => ['PUT', '/usuarios/{usuario}/permisos', []],
    'eliminar usuario' => ['DELETE', '/usuarios/{usuario}', []],
    'crear rol' => ['POST', '/roles', []],
    'editar rol' => ['PUT', '/roles/{rol}', []],
    'permisos rol' => ['PUT', '/roles/{rol}/permisos', []],
    'eliminar rol' => ['DELETE', '/roles/{rol}', []],
    'crear permiso' => ['POST', '/permisos', []],
    'editar permiso' => ['PUT', '/permisos/{permiso}', []],
    'eliminar permiso' => ['DELETE', '/permisos/{permiso}', []],
]);

test('no permite conceder permisos superiores mediante permisos directos o roles', function () {
    crearAdministradorDePrueba();
    $operador = Usuario::factory()->create();
    $destino = Usuario::factory()->create();
    otorgarPermisosDePrueba($operador, ['usuarios.gestionar_permisos', 'usuarios.gestionar_roles', 'roles.gestionar_permisos']);
    $permiso = Permiso::where('codigo', 'usuarios.restablecer_contrasena')->sole();
    $rol = Rol::create(['nombre' => 'Supervisión', 'codigo' => 'supervision']);
    $rol->permisos()->attach($permiso);

    $this->actingAs($operador)->put(route('usuarios.permisos', $destino), ['permisos' => [$permiso->id]])->assertForbidden();
    $this->put(route('usuarios.roles', $destino), ['roles' => [$rol->id]])->assertForbidden();
    $this->put(route('roles.permisos', $rol), ['permisos' => [$permiso->id]])->assertForbidden();

    expect($destino->fresh()->permisos)->toHaveCount(0);
    expect($destino->fresh()->roles)->toHaveCount(0);
    $this->assertDatabaseCount('registros_auditoria', 0);
});

test('no permite tomar control de una cuenta administradora con permisos de edicion de usuarios', function () {
    $administrador = crearAdministradorDePrueba();
    $operador = Usuario::factory()->create();
    otorgarPermisosDePrueba($operador, ['usuarios.editar', 'usuarios.restablecer_contrasena']);
    $anterior = $administrador->contrasena;

    $this->actingAs($operador)->put(route('usuarios.actualizar', $administrador), [
        'nombre' => 'Cambio no permitido', 'usuario' => $administrador->usuario, 'correo' => null,
    ])->assertForbidden();
    $this->put(route('usuarios.contrasena', $administrador), [
        'contrasena' => 'OtraClaveDePrueba12', 'contrasena_confirmation' => 'OtraClaveDePrueba12',
    ])->assertForbidden();

    expect($administrador->fresh()->contrasena)->toBe($anterior);
    $this->assertDatabaseCount('registros_auditoria', 0);
});

test('una consulta de usuarios no asigna permisos y oculta acciones no autorizadas', function () {
    crearAdministradorDePrueba();
    $lector = Usuario::factory()->create();
    otorgarPermisosDePrueba($lector, ['usuarios.ver']);

    $this->actingAs($lector)->get(route('usuarios.listar'))->assertSee('Cuentas del personal')->assertDontSee('Crear usuario');
    $this->get(route('usuarios.ver', $lector))->assertDontSee('Restablecer contraseña')->assertDontSee('Guardar roles');
    $this->get(route('auditoria.listar'))->assertForbidden();

    expect($lector->fresh()->permisos)->toHaveCount(1);
    expect($lector->fresh()->roles)->toHaveCount(0);
});

test('una cuenta administradora inactiva sigue protegida contra toma de control', function () {
    $administrador = crearAdministradorDePrueba();
    $administrador->activo = false;
    $administrador->save();
    crearAdministradorDePrueba();
    $operador = Usuario::factory()->create();
    otorgarPermisosDePrueba($operador, ['usuarios.restablecer_contrasena', 'usuarios.cambiar_estado']);
    $hashAnterior = $administrador->contrasena;

    $this->actingAs($operador)->put(route('usuarios.contrasena', $administrador), [
        'contrasena' => 'OtraClaveDePrueba12', 'contrasena_confirmation' => 'OtraClaveDePrueba12',
    ])->assertForbidden();
    $this->put(route('usuarios.estado', $administrador), ['activo' => true])->assertForbidden();

    expect($administrador->fresh()->contrasena)->toBe($hashAnterior);
    expect($administrador->fresh()->activo)->toBeFalse();
    $this->assertDatabaseCount('registros_auditoria', 0);
});
