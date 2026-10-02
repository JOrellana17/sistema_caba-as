<?php

use App\Models\Permiso;
use App\Models\Rol;

test('gestiona permisos personalizados sin alterar permisos del sistema', function () {
    $administrador = crearAdministradorDePrueba();

    $this->actingAs($administrador)->post(route('permisos.guardar'), ['codigo' => 'prueba.consultar', 'descripcion' => 'Consulta de prueba'])->assertRedirect();
    $permiso = Permiso::where('codigo', 'prueba.consultar')->sole();
    expect($administrador->tienePermiso('prueba.consultar'))->toBeTrue();
    $this->put(route('permisos.actualizar', $permiso), ['codigo' => 'prueba.consulta', 'descripcion' => 'Descripción actualizada'])->assertRedirect();
    $this->assertDatabaseHas('permisos', ['id' => $permiso->id, 'codigo' => 'prueba.consulta']);
    $this->delete(route('permisos.eliminar', $permiso))->assertRedirect();

    $this->assertModelMissing($permiso);
    $this->assertDatabaseCount('registros_auditoria', 3);
});

test('no renombra ni elimina codigos de permisos del sistema', function () {
    $administrador = crearAdministradorDePrueba();
    $permiso = Permiso::where('codigo', 'huespedes.buscar')->sole();

    $this->actingAs($administrador)->put(route('permisos.actualizar', $permiso), ['codigo' => 'otro.codigo'])->assertSessionHasErrors('codigo');
    $this->delete(route('permisos.eliminar', $permiso))->assertSessionHasErrors('permiso');

    $this->assertDatabaseHas('permisos', ['id' => $permiso->id, 'codigo' => 'huespedes.buscar']);
});

test('no elimina un permiso asignado a roles del personal', function () {
    $administrador = crearAdministradorDePrueba();
    $permiso = Permiso::create(['codigo' => 'prueba.consulta']);
    $rol = Rol::create(['nombre' => 'Consulta', 'codigo' => 'consulta']);
    $rol->permisos()->attach($permiso);

    $this->actingAs($administrador)->delete(route('permisos.eliminar', $permiso))->assertSessionHasErrors('permiso');

    $this->assertModelExists($permiso);
    $this->assertDatabaseHas('permiso_rol', ['rol_id' => $rol->id, 'permiso_id' => $permiso->id]);
});
