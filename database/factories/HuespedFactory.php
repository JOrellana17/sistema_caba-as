<?php

// Fábrica de datos ficticios para Huesped. Solo crea registros cuando un llamador solicita
// persistirlos; no configura cuentas reales.

namespace Database\Factories;

use App\Models\Huesped;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Huesped> */
class HuespedFactory extends Factory
{
    // Define atributos ficticios coherentes y relaciones necesarias para preparar registros de prueba;
    // no representa datos reales del establecimiento.
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'telefono' => null,
            'numero_identidad' => null,
            'activo' => true,
            'vip' => false,
            'frecuente' => false,
            'lista_negra' => false,
            'creado_por' => Usuario::factory(),
        ];
    }
}
