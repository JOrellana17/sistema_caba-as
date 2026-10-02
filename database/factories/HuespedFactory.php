<?php

namespace Database\Factories;

use App\Models\Huesped;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Huesped> */
class HuespedFactory extends Factory
{
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
