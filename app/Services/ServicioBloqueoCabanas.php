<?php

namespace App\Services;

use App\Models\Cabana;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class ServicioBloqueoCabanas
{
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
