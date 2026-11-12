<?php

// Historial de actividad de acceso; complementa la tabla técnica sessions sin crear otro mecanismo de
// autenticación.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SesionUsuario extends Model
{
    // Declara la tabla en español para no depender de la pluralización automática de Eloquent.
    protected $table = 'sesiones_usuarios';

    protected $guarded = ['id'];

    // Oculta estos atributos en arrays y JSON; no sustituye la autorización para acceder al modelo.
    protected $hidden = ['referencia'];

    // Define la relación con el registro padre con Usuario para consultar y cargar sus registros
    // mediante Eloquent. Incluye registros eliminados lógicamente para conservar la consulta
    // histórica.
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class)->withTrashed();
    }

    // Convierte los atributos persistidos a sus tipos PHP declarados para trabajar con fechas,
    // indicadores o datos estructurados de forma coherente.
    protected function casts(): array
    {
        return ['iniciada_en' => 'datetime', 'actividad_en' => 'datetime', 'vence_en' => 'datetime', 'finalizada_en' => 'datetime'];
    }
}
