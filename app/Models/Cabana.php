<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'descripcion', 'capacidad_base', 'capacidad_maxima', 'precio_base', 'precio_persona_adicional', 'precio_base_usd', 'precio_persona_adicional_usd', 'notas'])]
class Cabana extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'cabanas';

    public const ESTADOS = [
        'disponible' => 'Disponible',
        'ocupada' => 'Ocupada',
        'reservada' => 'Reservada',
        'mantenimiento' => 'Mantenimiento',
        'bloqueada' => 'Bloqueada',
    ];

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por')->withTrashed();
    }

    public function reservaciones(): HasMany
    {
        return $this->hasMany(Reservacion::class, 'cabana_id');
    }

    public function eventosCalendario(): HasMany
    {
        return $this->hasMany(EventoCalendario::class, 'cabana_id');
    }

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'capacidad_base' => 'integer',
            'capacidad_maxima' => 'integer',
            'precio_base' => 'decimal:2',
            'precio_base_usd' => 'decimal:2',
            'precio_persona_adicional_usd' => 'decimal:2',
            'precio_persona_adicional' => 'decimal:2',
        ];
    }
}
