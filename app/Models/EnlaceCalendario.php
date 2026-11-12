<?php

// Acceso revocable al feed ICS de una cabaña o rango; persiste el hash del token, no el token
// original.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnlaceCalendario extends Model
{
    // Declara la tabla en español para no depender de la pluralización automática de Eloquent.
    protected $table = 'enlaces_calendario';

    protected $guarded = ['id'];

    // Oculta estos atributos en arrays y JSON; no sustituye la autorización para acceder al modelo.
    protected $hidden = ['token_hash'];

    // Convierte los atributos persistidos a sus tipos PHP declarados para trabajar con fechas,
    // indicadores o datos estructurados de forma coherente.
    protected function casts(): array
    {
        return ['activo' => 'boolean', 'desde' => 'immutable_date', 'hasta' => 'immutable_date'];
    }

    // Define la relación con el registro padre con Cabana para consultar y cargar sus registros
    // mediante Eloquent. Incluye registros eliminados lógicamente para conservar la consulta
    // histórica.
    public function cabana(): BelongsTo
    {
        return $this->belongsTo(Cabana::class, 'cabana_id')->withTrashed();
    }
}
