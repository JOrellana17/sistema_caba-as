<?php

// Unidad independiente de alojamiento: capacidad, tarifas, equipamiento y relaciones de ocupación.

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

// Lista blanca de atributos asignables mediante fill/create; los campos sensibles se fijan explícitamente en los servicios.
#[Fillable(['nombre', 'descripcion', 'capacidad_base', 'capacidad_maxima', 'precio_base', 'precio_persona_adicional', 'precio_base_usd', 'precio_persona_adicional_usd', 'notas', 'camas', 'tiene_refrigerador', 'tiene_aire_acondicionado'])]
class Cabana extends Model
{
    use HasFactory;
    // Las eliminaciones fijan deleted_at; las consultas normales excluyen esas filas y restore() permite recuperarlas.
    use SoftDeletes;

    // Declara la tabla en español para no depender de la pluralización automática de Eloquent.
    protected $table = 'cabanas';

    public const TIPOS_CAMA = [
        'unipersonal' => 'Unipersonal', 'matrimonial' => 'Matrimonial', 'king' => 'King', 'queen' => 'Queen',
    ];

    public const MAXIMO_CAMAS_FORMULARIO = 200;

    // Devuelve el total de camas tanto para la lista individual actual como para el formato agregado
    // anterior; null significa sin registrar.
    public function totalCamas(): ?int
    {
        return $this->camas === null ? null : (array_is_list($this->camas) ? count($this->camas) : array_sum($this->camas));
    }

    // Entrega una lista individual de tipos de cama; convierte el formato agregado anterior sin
    // modificar el registro.
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

    // Agrupa la lista individual por tipo para mostrar cantidades, manteniendo compatibilidad con
    // datos agregados anteriores.
    public function cantidadesCamas(): ?array
    {
        if ($this->camas === null || ! array_is_list($this->camas)) {
            return $this->camas;
        }

        return array_replace(array_fill_keys(array_keys(self::TIPOS_CAMA), 0), array_count_values($this->camas));
    }

    // Relaciona únicamente ciclos de aseo sin confirmar; su existencia impide una entrada real.
    public function aseosPendientes(): HasMany
    {
        return $this->hasMany(AseoCabana::class, 'cabana_id')->whereNull('confirmado_en');
    }

    // Obtiene el ciclo de aseo más reciente para mostrar su última confirmación.
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
        return $this->hasMany(Reservacion::class, 'cabana_id');
    }

    // Define la relación uno a muchos con EventoCalendario para consultar y cargar sus registros
    // mediante Eloquent.
    public function eventosCalendario(): HasMany
    {
        return $this->hasMany(EventoCalendario::class, 'cabana_id');
    }

    // Convierte los atributos persistidos a sus tipos PHP declarados para trabajar con fechas,
    // indicadores o datos estructurados de forma coherente. Los importes se representan con dos
    // decimales.
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
