<?php

namespace Database\Factories;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    /**
     * Define una cuenta ficticia para las pruebas.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'usuario' => fake()->unique()->userName(),
            'correo' => fake()->unique()->safeEmail(),
            'correo_verificado_at' => now(),
            'contrasena' => Str::random(32),
            'activo' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Representa una cuenta que no puede acceder al sistema.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $atributos) => [
            'activo' => false,
        ]);
    }
}
