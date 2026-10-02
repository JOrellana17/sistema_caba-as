<?php

namespace App\Services;

use App\Models\Huesped;
use Illuminate\Validation\ValidationException;

class ServicioSeleccionHuesped
{
    // El caso de uso de destino debe autorizar su propia operación y ejecutar
    // esta relectura dentro de la transacción, junto con sus relaciones.
    public function validar(int $huespedId): Huesped
    {
        $huesped = Huesped::lockForUpdate()->find($huespedId);
        if (! $huesped) {
            throw ValidationException::withMessages(['huesped_id' => 'Selecciona un huésped existente que no esté eliminado.']);
        }

        // El negocio permite seleccionar inactivos/lista negra con advertencia.
        return $huesped;
    }
}
