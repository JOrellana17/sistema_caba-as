<?php

namespace Database\Factories;

use App\Models\Cabana;
use App\Models\Huesped;
use App\Models\Reservacion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Reservacion> */
class ReservacionFactory extends Factory
{
    public function definition(): array
    {
        // Importes y moneda ficticios solo para pruebas, sin definir reglas de cobro.
        return [
            'codigo' => (string) Str::uuid(),
            'cabana_id' => Cabana::factory(),
            'huesped_id' => Huesped::factory(),
            'fecha_entrada' => '2026-10-01',
            'fecha_salida' => '2026-10-02',
            'entrada_prevista' => '2026-10-01 20:00:00',
            'salida_prevista' => '2026-10-02 17:00:00',
            'cantidad_huespedes' => 2,
            'estado' => 'confirmada',
            'capacidad_base_aplicada' => 2,
            'noches' => 1,
            'personas_adicionales' => 0,
            'precio_base' => '123.45',
            'precio_persona_adicional' => '12.30',
            'subtotal' => '123.45',
            'moneda' => 'HNL',
            'creado_por' => Usuario::factory(),
        ];
    }
}
