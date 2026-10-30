<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'descripcion', 'capacidad_base', 'capacidad_maxima', 'precio_base', 'precio_persona_adicional', 'precio_base_usd', 'precio_persona_adicional_usd', 'notas', 'camas', 'tiene_refrigerador', 'tiene_aire_acondicionado'])]
class Cabana extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'cabanas';

    public const TIPOS_CAMA = [
        'unipersonal' => 'Unipersonal', 'matrimonial' => 'Matrimonial', 'king' => 'King', 'queen' => 'Queen',
    ];

    public const MAXIMO_CAMAS_FORMULARIO = 200;

    public function totalCamas(): ?int
    {
        return $this->camas === null ? null : (array_is_list($this->camas) ? count($this->camas) : array_sum($this->camas));
    }

    public function tiposCamas(): ?array
    {
        if ($this->camas === null || array_is_list($this->camas)) {
            return $this->camas;
        }
        $tipos = [];
        foreach (array_keys(self::TIPOS_CAMA) as $tipo) {
            array_push($tipos, ...array_fill(0, (int) ($this->camas[$tipo] ?? 0), $tipo));
        }

        return $tipos;
    }

    public function cantidadesCamas(): ?array
    {
        if ($this->camas === null || ! array_is_list($this->camas)) {
            return $this->camas;
        }

        return array_replace(array_fill_keys(array_keys(self::TIPOS_CAMA), 0), array_count_values($this->camas));
    }

    public function aseosPendientes(): HasMany
    {
        return $this->hasMany(AseoCabana::class, 'cabana_id')->whereNull('confirmado_en');
    }

    public function ultimoAseo(): HasOne
    {
        return $this->hasOne(AseoCabana::class, 'cabana_id')->latestOfMany();
    }

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
            'camas' => 'array',
            'tiene_refrigerador' => 'boolean',
            'tiene_aire_acondicionado' => 'boolean',
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
