<?php

// Servicio de bloqueo cabanas. Centraliza las operaciones descritas en sus métodos para que los
// controladores reutilicen las mismas reglas.

namespace App\Services;

use App\Models\Cabana;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class ServicioBloqueoCabanas
{
    // Ordena y bloquea las cabañas con SELECT FOR UPDATE dentro de una transacción. El orden común
    // reduce interbloqueos y serializa las operaciones de disponibilidad.
    public function adquirir(array $identificadores): Collection
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('El bloqueo de cabañas requiere una transacción.');
        }
        $identificadores = array_unique(array_map('intval', $identificadores));
        sort($identificadores);

        return Cabana::withTrashed()->whereIn('id', $identificadores)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }
}
