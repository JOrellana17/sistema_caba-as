<?php

// Servicio de seleccion huesped. Centraliza las operaciones descritas en sus métodos para que los
// controladores reutilicen las mismas reglas.

namespace App\Services;

use App\Models\Huesped;
use Illuminate\Validation\ValidationException;

class ServicioSeleccionHuesped
{
    // El caso de uso de destino debe autorizar su propia operación y ejecutar
    // esta relectura dentro de la transacción, junto con sus relaciones.
    // Relee y bloquea el huésped seleccionado para impedir referencias a fichas inexistentes o
    // eliminadas. La regla actual admite inactivos y lista negra con advertencia, sin crear
    // duplicados.
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
