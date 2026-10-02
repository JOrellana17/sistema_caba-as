<?php

use App\Models\RegistroAuditoria;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

test('muestra el formulario de acceso en espanol sin registro publico', function () {
    $this->get(route('sesion.mostrar'))
        ->assertSee('Iniciar sesión')
        ->assertSee('autocomplete="current-password"', false)
        ->assertSee('name="_token"', false);

    $this->post('/register')->assertNotFound();
});

test('el inicio requiere autenticacion', function () {
    $this->get(route('inicio'))->assertRedirectToRoute('sesion.mostrar');
    $this->getJson(route('inicio'))->assertUnauthorized();
});

test('autentica una cuenta activa regenera la sesion y audita sin credenciales', function () {
    $contrasena = Str::random(24);
    $usuario = Usuario::factory()->create(['contrasena' => $contrasena]);
    $this->withSession(['dato_previo' => 'conservar']);
    $sesionAnterior = session()->getId();

    $this->post(route('sesion.iniciar'), [
        'usuario' => $usuario->usuario,
        'contrasena' => $contrasena,
        'activo' => false,
        'rol' => 'administrador',
    ])->assertRedirectToRoute('inicio')->assertSessionHas('dato_previo', 'conservar');

    $this->assertAuthenticatedAs($usuario);
    expect(session()->getId())->not->toBe($sesionAnterior);
    $this->assertDatabaseHas('usuarios', ['id' => $usuario->id, 'activo' => true]);
    $this->assertDatabaseHas('registros_auditoria', [
        'usuario_id' => $usuario->id, 'modulo' => 'autenticacion', 'accion' => 'sesion_iniciada',
    ]);
    expect(RegistroAuditoria::first()->toJson())->not->toContain($contrasena, $usuario->contrasena, $usuario->correo);
    expect($usuario->toArray())->not->toHaveKeys(['contrasena', 'remember_token']);
});

test('conserva espacios en la contrasena', function () {
    $contrasena = ' '.Str::random(24).' ';
    $usuario = Usuario::factory()->create(['contrasena' => $contrasena]);

    $this->post(route('sesion.iniciar'), [
        'usuario' => $usuario->usuario, 'contrasena' => $contrasena,
    ])->assertRedirectToRoute('inicio');

    $this->assertAuthenticatedAs($usuario);
});

test('rechaza credenciales incorrectas sin guardar la contrasena en la sesion', function () {
    $usuario = Usuario::factory()->create();
    $contrasena = Str::random(24);

    $this->from(route('sesion.mostrar'))->post(route('sesion.iniciar'), [
        'usuario' => $usuario->usuario, 'contrasena' => $contrasena,
    ])->assertRedirectToRoute('sesion.mostrar')
        ->assertSessionHasErrors(['usuario' => 'No fue posible iniciar sesión con los datos proporcionados.'])
        ->assertSessionMissingInput('contrasena');

    $this->assertGuest();
    $this->assertDatabaseHas('registros_auditoria', ['accion' => 'acceso_fallido', 'usuario_id' => null]);
    expect(RegistroAuditoria::first()->toJson())->not->toContain($contrasena);
});

test('rechaza cuentas inactivas con el mismo mensaje de credenciales incorrectas', function () {
    $contrasena = Str::random(24);
    $usuario = Usuario::factory()->inactivo()->create(['contrasena' => $contrasena]);

    $this->post(route('sesion.iniciar'), [
        'usuario' => $usuario->usuario, 'contrasena' => $contrasena, 'activo' => true,
    ])->assertSessionHasErrors(['usuario' => 'No fue posible iniciar sesión con los datos proporcionados.']);

    $this->assertGuest();
    $this->assertDatabaseHas('usuarios', ['id' => $usuario->id, 'activo' => false]);
});

test('bloquea despues de cinco fallos y permite reintentar al terminar el minuto', function () {
    $this->freezeTime();
    $contrasena = Str::random(24);
    $usuario = Usuario::factory()->create(['contrasena' => $contrasena]);

    foreach (range(1, 5) as $intento) {
        $this->post(route('sesion.iniciar'), [
            'usuario' => $usuario->usuario, 'contrasena' => 'incorrecta',
        ])->assertSessionHasErrors('usuario');
    }

    $this->post(route('sesion.iniciar'), [
        'usuario' => $usuario->usuario, 'contrasena' => $contrasena,
    ])->assertSessionHasErrors(['usuario' => 'Demasiados intentos. Intenta nuevamente en 60 segundos.']);
    $this->assertGuest();
    $this->assertDatabaseCount('registros_auditoria', 5);

    $this->travel(61)->seconds();
    $this->post(route('sesion.iniciar'), [
        'usuario' => $usuario->usuario, 'contrasena' => $contrasena,
    ])->assertRedirectToRoute('inicio');
    $this->assertAuthenticatedAs($usuario);
});

test('recordarme permite recuperar la sesion mediante la cookie', function () {
    $contrasena = Str::random(24);
    $usuario = Usuario::factory()->create(['contrasena' => $contrasena]);
    $nombreCookie = Auth::guard()->getRecallerName();

    $respuesta = $this->post(route('sesion.iniciar'), [
        'usuario' => $usuario->usuario, 'contrasena' => $contrasena, 'recordar' => '1',
    ])->assertCookie($nombreCookie);

    $cookie = collect($respuesta->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === $nombreCookie);
    session()->flush();
    Auth::forgetGuards();
    $this->withUnencryptedCookie($nombreCookie, $cookie->getValue())->get(route('inicio'))->assertOk()->assertSee($usuario->nombre);
    $this->assertAuthenticatedAs($usuario);
});

test('cierra sesion elimina sus datos rota el token y audita', function () {
    $usuario = Usuario::factory()->create();
    $this->actingAs($usuario)->withSession(['dato_privado' => 'dato']);
    $tokenAnterior = session()->token();

    $this->post(route('sesion.cerrar'))->assertRedirectToRoute('sesion.mostrar')
        ->assertSessionMissing('dato_privado');

    $this->assertGuest();
    expect(session()->token())->not->toBe($tokenAnterior);
    $this->assertDatabaseHas('registros_auditoria', ['usuario_id' => $usuario->id, 'accion' => 'sesion_cerrada']);
});

test('revoca la sesion de una cuenta desactivada', function () {
    $usuario = Usuario::factory()->create();
    $this->actingAs($usuario)->get(route('inicio'))->assertOk();
    $usuario->forceFill(['activo' => false])->save();

    $this->get(route('inicio'))->assertRedirectToRoute('sesion.mostrar')
        ->assertSessionHasErrors('usuario');

    $this->assertGuest();
    $this->assertDatabaseHas('registros_auditoria', ['usuario_id' => $usuario->id, 'accion' => 'sesion_revocada']);
});

test('revoca con 401 las solicitudes JSON de cuentas desactivadas', function () {
    $usuario = Usuario::factory()->inactivo()->create();

    $this->actingAs($usuario)->getJson(route('inicio'))
        ->assertUnauthorized()->assertJson(['message' => 'Tu cuenta está inactiva.']);

    $this->assertGuest();
});

test('revoca una sesion cuyo hash de contrasena ha cambiado', function () {
    $usuario = Usuario::factory()->create();
    $this->actingAs($usuario)->get(route('inicio'))->assertOk();
    $usuario->contrasena = Str::random(24);
    $usuario->save();

    $this->get(route('inicio'))->assertRedirectToRoute('sesion.mostrar');

    $this->assertGuest();
});

test('rechaza iniciar sesion sin CSRF cuando se ejecuta el middleware real', function () {
    $contrasena = Str::random(24);
    $usuario = Usuario::factory()->create(['contrasena' => $contrasena]);
    $this->app->instance('env', 'local');

    $this->post(route('sesion.iniciar'), [
        'usuario' => $usuario->usuario, 'contrasena' => $contrasena,
    ])->assertStatus(419)->assertSee('La sesión del formulario venció.');

    $this->assertGuest();
    $this->assertDatabaseCount('registros_auditoria', 0);
});

test('rechaza cerrar sesion sin CSRF', function () {
    $usuario = Usuario::factory()->create();
    $this->app->instance('env', 'local');

    $this->actingAs($usuario)->post(route('sesion.cerrar'))->assertStatus(419);

    $this->assertAuthenticatedAs($usuario);
    $this->assertDatabaseCount('registros_auditoria', 0);
});

test('no permite cerrar sesion mediante GET', function () {
    $usuario = Usuario::factory()->create();

    $this->actingAs($usuario)->get(route('sesion.cerrar'))->assertMethodNotAllowed();

    $this->assertAuthenticatedAs($usuario);
});

test('valida campos requeridos con mensajes en espanol', function () {
    $this->post(route('sesion.iniciar'), [])->assertSessionHasErrors([
        'usuario' => 'Escribe tu nombre de usuario.',
        'contrasena' => 'Escribe tu contraseña.',
    ]);

    $this->assertGuest();
});

test('rechaza formatos invalidos sin persistir credenciales', function (array $datos, string $campo, string $mensaje) {
    $this->post(route('sesion.iniciar'), $datos)->assertSessionHasErrors([$campo => $mensaje])
        ->assertSessionMissingInput('contrasena');

    $this->assertGuest();
    $this->assertDatabaseCount('registros_auditoria', 0);
})->with([
    'usuario no textual' => [['usuario' => ['ana'], 'contrasena' => 'prueba'], 'usuario', 'El nombre de usuario debe ser texto.'],
    'usuario extenso' => [['usuario' => str_repeat('a', 101), 'contrasena' => 'prueba'], 'usuario', 'El nombre de usuario no puede superar los 100 caracteres.'],
    'contrasena no textual' => [['usuario' => 'ana', 'contrasena' => ['prueba']], 'contrasena', 'La contraseña debe ser texto.'],
    'contrasena extensa' => [['usuario' => 'ana', 'contrasena' => str_repeat('a', 1025)], 'contrasena', 'La contraseña no puede superar los 1024 caracteres.'],
    'recordar invalido' => [['usuario' => 'ana', 'contrasena' => 'prueba', 'recordar' => 'si'], 'recordar', 'La opción de recordar sesión no es válida.'],
]);

test('escapa el nombre de la cuenta en el inicio', function () {
    $nombre = '<script>alert("prueba")</script>';
    $usuario = Usuario::factory()->create(['nombre' => $nombre]);

    $this->actingAs($usuario)->get(route('inicio'))->assertSee($nombre)->assertDontSee($nombre, false);
});

test('no autentica mediante una inyeccion en el nombre de usuario', function () {
    Usuario::factory()->create();

    $this->post(route('sesion.iniciar'), [
        'usuario' => "' OR 1=1 --", 'contrasena' => 'prueba',
    ])->assertSessionHasErrors('usuario');

    $this->assertGuest();
});

test('redirige al inicio cuando una cuenta autenticada visita el acceso', function () {
    $usuario = Usuario::factory()->create();

    $this->actingAs($usuario)->get(route('sesion.mostrar'))->assertRedirectToRoute('inicio');
});

test('invalida la sesion si la contrasena cambia antes de la primera pagina protegida', function () {
    $contrasena = Str::random(24);
    $usuario = Usuario::factory()->create(['contrasena' => $contrasena]);
    $this->post(route('sesion.iniciar'), [
        'usuario' => $usuario->usuario, 'contrasena' => $contrasena,
    ])->assertRedirectToRoute('inicio');
    $usuario->contrasena = Str::random(24);
    $usuario->save();
    Auth::forgetGuards();

    $this->get(route('inicio'))->assertRedirectToRoute('sesion.mostrar');

    $this->assertGuest();
});
