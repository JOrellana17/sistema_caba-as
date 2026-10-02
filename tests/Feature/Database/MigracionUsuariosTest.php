<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\BaseDatosMySql;

test('la migracion conserva los identificadores y datos anteriores sin duplicar la tabla', function () {
    $base = BaseDatosMySql::crear(config('database.connections.mysql'));
    $base->conectar('prueba_migracion');
    DB::setDefaultConnection('prueba_migracion');

    try {
        $inicial = require database_path('migrations/0001_01_01_000000_create_users_table.php');
        $inicial->up();
        $hash = Hash::make(Str::random(24));
        DB::table('users')->insert([
            'id' => 37, 'name' => 'Persona existente', 'email' => 'existente@example.test',
            'password' => $hash, 'created_at' => '2026-09-01 00:00:00',
        ]);

        $adaptacion = require database_path('migrations/2026_09_30_031326_adaptar_usuarios_para_autenticacion.php');
        $adaptacion->up();

        expect(Schema::hasTable('users'))->toBeFalse();
        $this->assertDatabaseHas('usuarios', [
            'id' => 37, 'nombre' => 'Persona existente', 'correo' => 'existente@example.test',
            'contrasena' => $hash, 'usuario' => null, 'activo' => true,
            'created_at' => '2026-09-01 00:00:00',
        ]);
    } finally {
        DB::setDefaultConnection('mysql');
        DB::purge('prueba_migracion');
        $base->eliminar();
    }
});
