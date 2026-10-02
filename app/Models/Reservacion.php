<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['cabana_id', 'huesped_id', 'fecha_entrada', 'fecha_salida', 'cantidad_huespedes', 'notas'])]
class Reservacion extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'reservaciones';

    public const ESTADOS = [
        'por_confirmar' => 'Por confirmar',
        'pendiente' => 'Pendiente',
        'confirmada' => 'Confirmada',
        'hospedada' => 'Hospedada',
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada',
        'no_presentado' => 'No presentado',
    ];

    public const ESTADOS_QUE_BLOQUEAN = ['confirmada', 'hospedada'];

    public function cabana(): BelongsTo
    {
        return $this->belongsTo(Cabana::class, 'cabana_id')->withTrashed();
    }

    public function huesped(): BelongsTo
    {
        return $this->belongsTo(Huesped::class, 'huesped_id')->withTrashed();
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por')->withTrashed();
    }

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
