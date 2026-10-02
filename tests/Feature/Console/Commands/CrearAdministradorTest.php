<?php

use App\Models\Permiso;
use App\Models\Usuario;
use Database\Seeders\PermisosSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

test('crea el administrador solicitado sin correo inventado y con todos los permisos', function () {
    $contrasena = Str::random(8);

    $this->artisan('usuarios:crear-administrador', ['usuario' => 'administrador_prueba', '--longitud-minima' => 8])
        ->expectsQuestion('Contraseña inicial', $contrasena)
        ->expectsQuestion('Confirma la contraseña', $contrasena)
        ->assertExitCode(0);

    $usuario = Usuario::where('usuario', 'administrador_prueba')->sole();
    expect($usuario->correo)->toBeNull();
    expect(Hash::check($contrasena, $usuario->contrasena))->toBeTrue();
    expect($usuario->esAdministrador())->toBeTrue();
    expect($usuario->can('usuarios.gestionar_roles'))->toBeTrue();
    expect($usuario->can('huespedes.buscar'))->toBeTrue();
    $this->assertDatabaseHas('registros_auditoria', ['registro_id' => $usuario->id, 'accion' => 'administrador_creado_por_consola']);
});

test('el alta administrativa no reemplaza una cuenta existente', function () {
    $usuario = Usuario::factory()->create(['usuario' => 'existente']);
    $anterior = $usuario->contrasena;
    $contrasena = Str::random(24);

    $this->artisan('usuarios:crear-administrador', ['usuario' => 'existente'])
        ->expectsQuestion('Contraseña inicial', $contrasena)
        ->expectsQuestion('Confirma la contraseña', $contrasena)
        ->assertExitCode(1);

    expect($usuario->fresh()->contrasena)->toBe($anterior);
    $this->assertDatabaseCount('usuarios', 1);
    $this->assertDatabaseCount('registros_auditoria', 0);
});

test('el catalogo de permisos puede sembrarse nuevamente sin duplicados ni nuevas cuentas', function () {
    $administrador = crearAdministradorDePrueba();
    $cantidad = Permiso::count();

    $this->artisan('db:seed', ['--class' => PermisosSeeder::class])->assertExitCode(0);

    $this->assertDatabaseCount('permisos', $cantidad);
    $this->assertDatabaseCount('roles', 1);
    $this->assertDatabaseCount('usuarios', 1);
    expect($administrador->fresh()->roles)->toHaveCount(1);
});
