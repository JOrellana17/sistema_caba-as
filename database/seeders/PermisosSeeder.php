<?php

// Semilla de datos iniciales. Se ejecuta mediante Artisan cuando el operador lo solicita; no se debe
// utilizar para reiniciar una base existente.

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermisosSeeder extends Seeder
{
    // Crea o conserva los permisos técnicos y roles de base, completa los del administrador y respeta
    // las asignaciones existentes del empleado. No crea contraseñas ni cuentas.
    public function run(): void
    {
        // Las escrituras de este bloque se confirman juntas; una excepción revierte sus cambios en la base de datos.
        DB::transaction(function () {
            $administrador = Rol::firstOrCreate(['codigo' => 'administrador'], [
                'nombre' => 'Administrador',
                'descripcion' => 'Administración del sistema.',
            ]);

            foreach (config('permisos') as $modulo => $acciones) {
                foreach ($acciones as $accion) {
                    $permiso = Permiso::firstOrNew(['codigo' => $modulo.'.'.$accion]);
                    $permiso->protegido = true;
                    $permiso->save();
                }
            }

            $administrador->permisos()->syncWithoutDetaching(Permiso::pluck('id'));

            $empleado = Rol::firstOrCreate(['codigo' => 'empleado'], [
                'nombre' => 'Empleado',
                'descripcion' => 'Operación de huéspedes y reservaciones, sin administración ni finanzas.',
            ]);
            if ($empleado->wasRecentlyCreated) {
                $empleado->permisos()->sync(Permiso::whereIn('codigo', config('roles.empleado'))->pluck('id'));
            }
            $aseador = Rol::firstOrCreate(['codigo' => 'aseador'], [
                'nombre' => 'Aseador', 'descripcion' => 'Consulta y confirmación de aseo de cabañas.',
            ]);
            $aseador->permisos()->syncWithoutDetaching(Permiso::whereIn('codigo', config('roles.aseador'))->pluck('id'));
        });
    }
}
