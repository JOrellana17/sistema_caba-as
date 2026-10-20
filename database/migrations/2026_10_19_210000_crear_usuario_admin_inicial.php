<?php

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\PermisosSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Las pruebas crean sus propios usuarios; no deben heredar esta cuenta.
        if (app()->environment('testing')) {
            return;
        }

        DB::transaction(function () {
            (new PermisosSeeder)->run();

            if (Usuario::withTrashed()->where('usuario', 'admin')->exists()) {
                return;
            }

            $admin = Usuario::create([
                'nombre' => 'Administrador',
                'usuario' => 'admin',
                'correo' => null,
                'contrasena' => 'admin123',
            ]);
            $admin->roles()->attach(Rol::where('codigo', 'administrador')->sole());
        });
    }

    public function down(): void
    {
        // Se conserva la cuenta: podría tener auditoría y registros asociados.
    }
};
