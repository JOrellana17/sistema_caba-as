<?php

// Semilla de datos iniciales. Se ejecuta mediante Artisan cuando el operador lo solicita; no se debe
// utilizar para reiniciar una base existente.

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    // Ejecuta la semilla de permisos; las cuentas reales se crean mediante los comandos interactivos.
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PermisosSeeder::class);
        // Las cuentas reales se crean de forma interactiva, sin credenciales predeterminadas.
    }
}
