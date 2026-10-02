<?php

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\PermisosSeeder;

function crearAdministradorDePrueba(): Usuario
{
    (new PermisosSeeder)->run();
    $usuario = Usuario::factory()->create();
    $usuario->roles()->attach(Rol::where('codigo', 'administrador')->sole());

    return $usuario;
}

function otorgarPermisosDePrueba(Usuario $usuario, array $codigos): void
{
    $usuario->permisos()->syncWithoutDetaching(Permiso::whereIn('codigo', $codigos)->pluck('id'));
}
