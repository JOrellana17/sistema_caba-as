<?php

// Configuración de una fuente iCalendar; cifra la URL privada y conserva su versión y estado de
// lectura.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuenteCalendario extends Model
{
    // Declara la tabla en español para no depender de la pluralización automática de Eloquent.
    protected $table = 'fuentes_calendario';

    protected $guarded = ['id'];

    protected $attributes = ['version' => 1];

    // Oculta estos atributos en arrays y JSON; no sustituye la autorización para acceder al modelo.
    protected $hidden = ['url', 'etag', 'ultima_modificacion'];

    // Convierte los atributos persistidos a sus tipos PHP declarados para trabajar con fechas,
    // indicadores o datos estructurados de forma coherente. La URL se cifra al persistirla y se
    // descifra al leerla con la clave de la aplicación.
    protected function casts(): array
    {
        return ['url' => 'encrypted', 'activa' => 'boolean', 'ultima_correcta' => 'immutable_datetime', 'proxima_consulta' => 'immutable_datetime', 'cobertura_desde' => 'immutable_date', 'cobertura_hasta' => 'immutable_date'];
    }

    // Define la relación con el registro padre con Cabana para consultar y cargar sus registros
    // mediante Eloquent. Incluye registros eliminados lógicamente para conservar la consulta
    // histórica.
    public function cabana(): BelongsTo
    {
        return $this->belongsTo(Cabana::class, 'cabana_id')->withTrashed();
    }

    // Define la relación uno a muchos con EventoCalendario para consultar y cargar sus registros
    // mediante Eloquent.
    public function eventos(): HasMany
    {
        return $this->hasMany(EventoCalendario::class, 'fuente_id');
    }
}
