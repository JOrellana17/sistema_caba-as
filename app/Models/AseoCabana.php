<?php

// Ciclo operativo iniciado al finalizar una estancia y confirmado por personal autorizado.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AseoCabana extends Model
{
    // Declara la tabla en español para no depender de la pluralización automática de Eloquent.
    protected $table = 'aseos_cabanas';

    // Define la relación con el registro padre con Cabana para consultar y cargar sus registros
    // mediante Eloquent. Incluye registros eliminados lógicamente para conservar la consulta
    // histórica.
    public function cabana(): BelongsTo
    {
        return $this->belongsTo(Cabana::class, 'cabana_id')->withTrashed();
    }

    // Define la relación con el registro padre con Reservacion para consultar y cargar sus registros
    // mediante Eloquent. Incluye registros eliminados lógicamente para conservar la consulta
    // histórica.
    public function reservacion(): BelongsTo
    {
        return $this->belongsTo(Reservacion::class, 'reservacion_id')->withTrashed();
    }

    // Define la relación con el registro padre con Usuario para consultar y cargar sus registros
    // mediante Eloquent. Incluye registros eliminados lógicamente para conservar la consulta
    // histórica.
    public function confirmador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'confirmado_por')->withTrashed();
    }

    // Convierte los atributos persistidos a sus tipos PHP declarados para trabajar con fechas,
    // indicadores o datos estructurados de forma coherente.
    protected function casts(): array
    {
        return ['iniciado_en' => 'immutable_datetime', 'confirmado_en' => 'immutable_datetime'];
    }
}
