<?php

// Ficha única del cliente reutilizada en reservaciones, con columnas normalizadas para búsqueda.

namespace App\Models;

use App\Support\NormalizadorHuesped;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// Lista blanca de atributos asignables mediante fill/create; los campos sensibles se fijan explícitamente en los servicios.
#[Fillable(['nombre', 'telefono', 'numero_identidad', 'nacionalidad', 'notas', 'notas_incidentes', 'vip'])]
// Oculta estos atributos en arrays y JSON; no sustituye la autorización para acceder al modelo.
#[Hidden(['nombre_busqueda', 'telefono_busqueda', 'identidad_busqueda', 'numero_identidad', 'notas', 'notas_incidentes'])]
class Huesped extends Model
{
    use HasFactory;
    // Las eliminaciones fijan deleted_at; las consultas normales excluyen esas filas y restore() permite recuperarlas.
    use SoftDeletes;

    // Declara la tabla en español para no depender de la pluralización automática de Eloquent.
    protected $table = 'huespedes';

    // Registra el evento saving de Eloquent para recalcular columnas de búsqueda cada vez que se
    // guarda la ficha, preservando los datos originales.
    protected static function booted(): void
    {
        static::saving(function (Huesped $huesped) {
            $huesped->nombre_busqueda = NormalizadorHuesped::nombre($huesped->nombre);
            $huesped->telefono_busqueda = $huesped->telefono === null ? null : NormalizadorHuesped::telefono($huesped->telefono);
            $huesped->identidad_busqueda = $huesped->numero_identidad === null ? null : NormalizadorHuesped::identidad($huesped->numero_identidad);
        });
    }

    // Define la relación con el registro padre con Usuario para consultar y cargar sus registros
    // mediante Eloquent. Incluye registros eliminados lógicamente para conservar la consulta
    // histórica.
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por')->withTrashed();
    }

    // Define la relación uno a muchos con Reservacion para consultar y cargar sus registros mediante
    // Eloquent.
    public function reservaciones(): HasMany
    {
        return $this->hasMany(Reservacion::class, 'huesped_id');
    }

    // Convierte los atributos persistidos a sus tipos PHP declarados para trabajar con fechas,
    // indicadores o datos estructurados de forma coherente.
    protected function casts(): array
    {
        return ['activo' => 'boolean', 'vip' => 'boolean', 'frecuente' => 'boolean', 'lista_negra' => 'boolean'];
    }
}
