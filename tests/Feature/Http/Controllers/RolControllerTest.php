<?php

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;

test('crea edita y elimina un rol sin asignaciones', function () {
    $administrador = crearAdministradorDePrueba();

    $this->actingAs($administrador)->post(route('roles.guardar'), [
        'nombre' => 'Consulta', 'codigo' => 'consulta', 'descripcion' => 'Solo consultas',
    ])->assertRedirect();
    $rol = Rol::where('codigo', 'consulta')->sole();
    expect($rol->permisos)->toHaveCount(0);
    $this->put(route('roles.actualizar', $rol), [
        'nombre' => 'Consulta interna', 'codigo' => 'consulta_interna', 'descripcion' => null,
    ])->assertRedirect();
    $this->assertDatabaseHas('roles', ['id' => $rol->id, 'codigo' => 'consulta_interna']);
    $this->delete(route('roles.eliminar', $rol))->assertRedirect();

    $this->assertModelMissing($rol);
    $this->assertDatabaseCount('registros_auditoria', 3);
});

test('protege el rol administrador y sus permisos', function () {
    $administrador = crearAdministradorDePrueba();
    $rol = Rol::where('codigo', 'administrador')->sole();
    $cantidad = $rol->permisos()->count();

    $this->actingAs($administrador)->put(route('roles.actualizar', $rol), [
        'nombre' => 'Renombrado', 'codigo' => 'otro',
    ])->assertSessionHasErrors('codigo');
    $this->delete(route('roles.eliminar', $rol))->assertSessionHasErrors('rol');
    $this->put(route('roles.permisos', $rol), ['permisos' => []])->assertSessionHasErrors('rol');

    expect($rol->fresh()->codigo)->toBe('administrador');
    expect($rol->permisos()->count())->toBe($cantidad);
    $this->assertDatabaseCount('registros_auditoria', 0);
});

test('no elimina un rol asignado incluso si su usuario fue eliminado logicamente', function () {
    $administrador = crearAdministradorDePrueba();
    $rol = Rol::create(['nombre' => 'Consulta', 'codigo' => 'consulta']);
    $usuario = Usuario::factory()->create();
    $usuario->roles()->attach($rol);
    $usuario->delete();

    $this->actingAs($administrador)->delete(route('roles.eliminar', $rol))->assertSessionHasErrors('rol');

    $this->assertModelExists($rol);
    $this->assertDatabaseHas('rol_usuario', ['rol_id' => $rol->id, 'usuario_id' => $usuario->id]);
});

test('no permite asignar permisos al crear o editar los datos del rol', function () {
    $administrador = crearAdministradorDePrueba();
    $permiso = Permiso::where('codigo', 'usuarios.ver')->sole();

    $this->actingAs($administrador)->post(route('roles.guardar'), [
        'nombre' => 'Intento', 'codigo' => 'intento', 'permisos' => [$permiso->id],
    ])->assertSessionHasErrors('permisos');

    $this->assertDatabaseMissing('roles', ['codigo' => 'intento']);
});
