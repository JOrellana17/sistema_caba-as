<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoCalendario extends Model
{
    protected $table = 'eventos_calendario';

    protected $guarded = ['id'];

    protected $hidden = ['uid', 'identidad'];

    protected function casts(): array
    {
        return ['inicio' => 'immutable_datetime', 'fin' => 'immutable_datetime', 'bloquea' => 'boolean', 'fecha_completa' => 'boolean', 'conflicto' => 'boolean', 'discrepancia' => 'boolean', 'ausente_desde' => 'immutable_datetime', 'visto_en' => 'immutable_datetime'];
    }

    public function cabana(): BelongsTo
    {
        return $this->belongsTo(Cabana::class, 'cabana_id')->withTrashed();
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteCalendario::class, 'fuente_id');
    }

    public function reservacion(): BelongsTo
    {
        return $this->belongsTo(Reservacion::class, 'reservacion_id')->withTrashed();
    }
}
