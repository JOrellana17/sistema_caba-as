<?php

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

test('crea una cuenta mediante consola con hash y registro de auditoria', function () {
    $contrasena = Str::random(24);

    $this->artisan('usuarios:crear')
        ->expectsQuestion('Nombre completo', 'Ana Prueba')
        ->expectsQuestion('Nombre de usuario', 'ana.prueba')
        ->expectsQuestion('Correo electrónico', 'ana@example.test')
        ->expectsQuestion('Contraseña (mínimo 12 caracteres; máximo 72 bytes)', $contrasena)
        ->expectsQuestion('Confirma la contraseña', $contrasena)
        ->expectsOutput('Cuenta creada. No se han asignado roles ni permisos.')
        ->assertExitCode(0);

    $usuario = Usuario::sole();
    expect(Hash::check($contrasena, $usuario->contrasena))->toBeTrue();
    $this->assertDatabaseHas('usuarios', ['usuario' => 'ana.prueba', 'activo' => true]);
    $this->assertDatabaseHas('registros_auditoria', ['usuario_id' => $usuario->id, 'accion' => 'cuenta_creada_por_consola']);
});

test('rechaza altas invalidas sin crear cuenta ni auditoria', function (string $usuario, string $correo, string $contrasena, string $confirmacion) {
    $this->artisan('usuarios:crear')
        ->expectsQuestion('Nombre completo', 'Ana Prueba')
        ->expectsQuestion('Nombre de usuario', $usuario)
        ->expectsQuestion('Correo electrónico', $correo)
        ->expectsQuestion('Contraseña (mínimo 12 caracteres; máximo 72 bytes)', $contrasena)
        ->expectsQuestion('Confirma la contraseña', $confirmacion)
        ->assertExitCode(1);

    $this->assertDatabaseCount('usuarios', 0);
    $this->assertDatabaseCount('registros_auditoria', 0);
})->with([
    'usuario invalido' => ['Ana con espacios', 'ana@example.test', 'SoloParaPruebas123', 'SoloParaPruebas123'],
    'correo invalido' => ['ana', 'incorrecto', 'SoloParaPruebas123', 'SoloParaPruebas123'],
    'contrasena corta' => ['ana', 'ana@example.test', 'corta', 'corta'],
    'confirmacion distinta' => ['ana', 'ana@example.test', 'SoloParaPruebas123', 'distinta'],
    'limite bcrypt multibyte' => ['ana', 'ana@example.test', str_repeat('ñ', 37), str_repeat('ñ', 37)],
]);

test('rechaza nombres de acceso duplicados sin modificar la cuenta existente', function () {
    $usuario = Usuario::factory()->create(['usuario' => 'ana']);
    $hashAnterior = $usuario->contrasena;
    $contrasena = Str::random(24);

    $this->artisan('usuarios:crear')
        ->expectsQuestion('Nombre completo', 'Otra persona')
        ->expectsQuestion('Nombre de usuario', 'ana')
        ->expectsQuestion('Correo electrónico', 'otra@example.test')
        ->expectsQuestion('Contraseña (mínimo 12 caracteres; máximo 72 bytes)', $contrasena)
        ->expectsQuestion('Confirma la contraseña', $contrasena)
        ->assertExitCode(1);

    $this->assertDatabaseCount('usuarios', 1);
    expect($usuario->fresh()->contrasena)->toBe($hashAnterior);
    $this->assertDatabaseCount('registros_auditoria', 0);
});
