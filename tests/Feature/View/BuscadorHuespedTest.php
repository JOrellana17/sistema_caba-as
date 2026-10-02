<?php

use App\Models\Huesped;
use App\Models\Usuario;
use Illuminate\Support\ViewErrorBag;

test('el componente conserva una seleccion validada por el servidor y permite cambiarla', function () {
    $usuario = crearAdministradorDePrueba();
    $huesped = Huesped::factory()->create(['nombre' => 'Cliente previamente elegido', 'telefono' => '00123', 'numero_identidad' => '00-ABC', 'activo' => false, 'lista_negra' => true]);
    $this->actingAs($usuario);
    $this->app['view']->share('errors', new ViewErrorBag);
    $this->blade('<x-buscador-huesped :seleccion="$huesped" />', ['huesped' => $huesped, 'errors' => new ViewErrorBag])
        ->assertSee('name="huesped_id" value="'.$huesped->id.'"', false)
        ->assertSee('Cliente previamente elegido')->assertSee('00-ABC')
        ->assertSee('Advertencia: inactivo')->assertSee('Advertencia: lista negra')
        ->assertSee('Cambiar cliente');
    $this->assertDatabaseCount('huespedes', 1);
});

test('el componente no expone una seleccion a quien carece de permiso de buscar', function () {
    $huesped = Huesped::factory()->create(['numero_identidad' => '00-PRIVADO']);
    $this->actingAs(Usuario::factory()->create());
    $this->blade('<x-buscador-huesped :seleccion="$huesped" />', ['huesped' => $huesped, 'errors' => new ViewErrorBag])
        ->assertDontSee('00-PRIVADO')->assertDontSee('name="huesped_id"', false);
});
