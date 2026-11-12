<?php

// Bloqueo de ocupación local o externo, con identidad estable y posible vínculo a una reservación.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoCalendario extends Model
{
    // Declara la tabla en español para no depender de la pluralización automática de Eloquent.
    protected $table = 'eventos_calendario';

    protected $guarded = ['id'];

    // Oculta estos atributos en arrays y JSON; no sustituye la autorización para acceder al modelo.
    protected $hidden = ['uid', 'identidad'];

    // Convierte los atributos persistidos a sus tipos PHP declarados para trabajar con fechas,
    // indicadores o datos estructurados de forma coherente.
    protected function casts(): array
    {
        return ['inicio' => 'immutable_datetime', 'fin' => 'immutable_datetime', 'bloquea' => 'boolean', 'fecha_completa' => 'boolean', 'conflicto' => 'boolean', 'discrepancia' => 'boolean', 'ausente_desde' => 'immutable_datetime', 'visto_en' => 'immutable_datetime'];
    }

    // Define la relación con el registro padre con Cabana para consultar y cargar sus registros
    // mediante Eloquent. Incluye registros eliminados lógicamente para conservar la consulta
    // histórica.
    public function cabana(): BelongsTo
    {
        return $this->belongsTo(Cabana::class, 'cabana_id')->withTrashed();
    }

    // Define la relación con el registro padre con FuenteCalendario para consultar y cargar sus
    // registros mediante Eloquent.
    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteCalendario::class, 'fuente_id');
    }

    // Define la relación con el registro padre con Reservacion para consultar y cargar sus registros
    // mediante Eloquent. Incluye registros eliminados lógicamente para conservar la consulta
    // histórica.
    public function reservacion(): BelongsTo
    {
        return $this->belongsTo(Reservacion::class, 'reservacion_id')->withTrashed();
    }
}
