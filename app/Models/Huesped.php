<?php

namespace App\Models;

use App\Support\NormalizadorHuesped;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'telefono', 'numero_identidad', 'nacionalidad', 'notas', 'notas_incidentes', 'vip'])]
#[Hidden(['nombre_busqueda', 'telefono_busqueda', 'identidad_busqueda', 'numero_identidad', 'notas', 'notas_incidentes'])]
class Huesped extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'huespedes';

    protected static function booted(): void
    {
        static::saving(function (Huesped $huesped) {
            $huesped->nombre_busqueda = NormalizadorHuesped::nombre($huesped->nombre);
            $huesped->telefono_busqueda = $huesped->telefono === null ? null : NormalizadorHuesped::telefono($huesped->telefono);
            $huesped->identidad_busqueda = $huesped->numero_identidad === null ? null : NormalizadorHuesped::identidad($huesped->numero_identidad);
        });
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por')->withTrashed();
    }

    public function reservaciones(): HasMany
    {
        return $this->hasMany(Reservacion::class, 'huesped_id');
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'vip' => 'boolean', 'frecuente' => 'boolean', 'lista_negra' => 'boolean'];
    }
}
