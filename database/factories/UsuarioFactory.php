<?php

// Fábrica de datos ficticios para Usuario. Solo crea registros cuando un llamador solicita
// persistirlos; no configura cuentas reales.

namespace Database\Factories;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    // Define atributos ficticios coherentes y relaciones necesarias para preparar registros de prueba;
    // no representa datos reales del establecimiento.
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

    // Prepara una variante de la fábrica con la cuenta desactivada para comprobar restricciones de
    // acceso.
    /**
     * Representa una cuenta que no puede acceder al sistema.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $atributos) => [
            'activo' => false,
        ]);
    }

    // Prepara una cuenta con el máximo de intentos y fecha de bloqueo para verificar el flujo
    // administrativo de desbloqueo.
    /**
     * Representa una cuenta bloqueada por intentos fallidos de acceso.
     */
    public function bloqueado(): static
    {
        return $this->state(fn (array $atributos) => [
            'intentos_fallidos' => Usuario::MAXIMO_INTENTOS,
            'bloqueado_at' => now(),
        ]);
    }
}
