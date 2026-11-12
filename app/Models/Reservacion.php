<?php

// Estancia desde su solicitud hasta el cierre, con estados, horarios y copia histórica del precio.

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

// Lista blanca de atributos asignables mediante fill/create; los campos sensibles se fijan explícitamente en los servicios.
#[Fillable(['cabana_id', 'huesped_id', 'fecha_entrada', 'fecha_salida', 'cantidad_huespedes', 'notas'])]
class Reservacion extends Model
{
    use HasFactory;
    // Las eliminaciones fijan deleted_at; las consultas normales excluyen esas filas y restore() permite recuperarlas.
    use SoftDeletes;

    // Declara la tabla en español para no depender de la pluralización automática de Eloquent.
    protected $table = 'reservaciones';

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'confirmada' => 'Confirmada',
        'hospedada' => 'Hospedada',
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada',
        'no_presentado' => 'No presentado',
    ];

    public const ESTADOS_QUE_BLOQUEAN = ['confirmada', 'hospedada'];

    public const ESTADOS_ACTIVOS = ['pendiente', 'confirmada', 'hospedada'];

    // Define la relación con el registro padre con Cabana para consultar y cargar sus registros
    // mediante Eloquent. Incluye registros eliminados lógicamente para conservar la consulta
    // histórica.
    public function cabana(): BelongsTo
    {
        return $this->belongsTo(Cabana::class, 'cabana_id')->withTrashed();
    }

    // Define la relación con el registro padre con Huesped para consultar y cargar sus registros
    // mediante Eloquent. Incluye registros eliminados lógicamente para conservar la consulta
    // histórica.
    public function huesped(): BelongsTo
    {
        return $this->belongsTo(Huesped::class, 'huesped_id')->withTrashed();
    }

    // Define la relación con el registro padre con Usuario para consultar y cargar sus registros
    // mediante Eloquent. Incluye registros eliminados lógicamente para conservar la consulta
    // histórica.
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por')->withTrashed();
    }

    // Define la relación uno a uno con EventoCalendario para consultar y cargar sus registros mediante
    // Eloquent.
    public function eventoCalendario(): HasOne
    {
        return $this->hasOne(EventoCalendario::class, 'reservacion_id');
    }

    // Define la relación uno a uno con AseoCabana para consultar y cargar sus registros mediante
    // Eloquent.
    public function aseo(): HasOne
    {
        return $this->hasOne(AseoCabana::class, 'reservacion_id');
    }

    // Convierte los atributos persistidos a sus tipos PHP declarados para trabajar con fechas,
    // indicadores o datos estructurados de forma coherente. Los importes se representan con dos
    // decimales.
    protected function casts(): array
    {
        return [
            'fecha_entrada' => 'immutable_date:Y-m-d',
            'fecha_salida' => 'immutable_date:Y-m-d',
            'entrada_prevista' => 'immutable_datetime',
            'salida_prevista' => 'immutable_datetime',
            'cantidad_huespedes' => 'integer',
            'capacidad_base_aplicada' => 'integer',
            'noches' => 'integer',
            'personas_adicionales' => 'integer',
            'precio_base' => 'decimal:2',
            'precio_persona_adicional' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'fecha_confirmacion' => 'immutable_datetime',
            'fecha_entrada_registrada' => 'immutable_datetime',
            'fecha_salida_registrada' => 'immutable_datetime',
            'fecha_cancelacion' => 'immutable_datetime',
            'fecha_no_presentado' => 'immutable_datetime',
        ];
    }
}
