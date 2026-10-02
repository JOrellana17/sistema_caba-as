<?php

namespace Database\Factories;

use App\Models\Cabana;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cabana>
 */
class CabanaFactory extends Factory
{
    public function definition(): array
    {
        // Valores ficticios exclusivamente para pruebas; no son tarifas del negocio.
        return [
            'nombre' => 'Cabaña de prueba '.fake()->unique()->numberBetween(1, 99999),
            'slug' => (string) Str::uuid(),
            'capacidad_base' => 2,
            'capacidad_maxima' => 4,
            'precio_base' => '123.45',
            'precio_persona_adicional' => '12.30',
            'estado' => 'disponible',
            'activa' => true,
            'creado_por' => Usuario::factory(),
        ];
    }
}
