<?php

use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

test('permite iniciar sesion con un token CSRF valido y sin cookie de recuerdo por defecto', function () {
    $contrasena = Str::random(24);
    $usuario = Usuario::factory()->create(['contrasena' => $contrasena]);
    $token = Str::random(40);
    $this->app->instance('env', 'local');

    $this->withSession(['_token' => $token])->post(route('sesion.iniciar'), [
        'usuario' => $usuario->usuario, 'contrasena' => $contrasena, '_token' => $token,
    ])->assertRedirectToRoute('inicio')->assertCookieMissing(Auth::guard()->getRecallerName());

    $this->assertAuthenticatedAs($usuario);
});

test('muestra el formulario despues de rechazar un usuario con formato de arreglo', function () {
    $this->from(route('sesion.mostrar'))->followingRedirects()->post(route('sesion.iniciar'), [
        'usuario' => ['incorrecto'], 'contrasena' => Str::random(24),
    ])->assertSee('El nombre de usuario debe ser texto.');
});

test('escapa el nombre de usuario recordado al rechazar el acceso', function () {
    $usuario = '<script>alert("prueba")</script>';
    $this->from(route('sesion.mostrar'))->followingRedirects()->post(route('sesion.iniciar'), [
        'usuario' => $usuario, 'contrasena' => Str::random(24),
    ])->assertSee($usuario)->assertDontSee($usuario, false);
});
