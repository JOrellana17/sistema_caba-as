<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermisosSeeder extends Seeder
{
    public function run(): void
    {
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
        });
    }
}
