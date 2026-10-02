<?php

use App\Models\Usuario;

test('el inicio muestra tarjetas con accesos reales y retira la explicacion de permisos', function () {
    $respuesta = $this->actingAs(crearAdministradorDePrueba())->get(route('inicio'))->assertOk()
        ->assertSee('data-acceso-modulo', false)->assertSee('Nueva reservación')->assertSee('Cabañas')->assertSee('Auditoría')
        ->assertDontSee('Los módulos administrativos se habilitarán');
    expect(substr_count($respuesta->getContent(), 'data-acceso-modulo'))->toBe(10);
});

test('las tarjetas de inicio respetan los permisos de cada usuario', function () {
    crearAdministradorDePrueba();
    $usuario = Usuario::factory()->create();
    otorgarPermisosDePrueba($usuario, ['reservaciones.crear', 'huespedes.buscar', 'cabanas.ver_disponibilidad']);
    $respuesta = $this->actingAs($usuario)->get(route('inicio'))->assertOk()
        ->assertSee('Nueva reservación')->assertSee(route('cabanas.disponibilidad'))
        ->assertDontSee(route('usuarios.listar'))->assertDontSee(route('roles.listar'))->assertDontSee('href="'.route('reservaciones.listar').'"', false);
    expect(substr_count($respuesta->getContent(), 'data-acceso-modulo'))->toBe(3);
    $this->get(route('usuarios.listar'))->assertForbidden();
});
