<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AseoCabana extends Model
{
    protected $table = 'aseos_cabanas';

    public function cabana(): BelongsTo
    {
        return $this->belongsTo(Cabana::class, 'cabana_id')->withTrashed();
    }

    public function reservacion(): BelongsTo
    {
        return $this->belongsTo(Reservacion::class, 'reservacion_id')->withTrashed();
    }

    public function confirmador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'confirmado_por')->withTrashed();
    }

    protected function casts(): array
    {
        return ['iniciado_en' => 'immutable_datetime', 'confirmado_en' => 'immutable_datetime'];
    }
}
