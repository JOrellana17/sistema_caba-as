<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuenteCalendario extends Model
{
    protected $table = 'fuentes_calendario';

    protected $guarded = ['id'];

    protected $attributes = ['version' => 1];

    protected $hidden = ['url', 'etag', 'ultima_modificacion'];

    protected function casts(): array
    {
        return ['url' => 'encrypted', 'activa' => 'boolean', 'ultima_correcta' => 'immutable_datetime', 'proxima_consulta' => 'immutable_datetime', 'cobertura_desde' => 'immutable_date', 'cobertura_hasta' => 'immutable_date'];
    }

    public function cabana(): BelongsTo
    {
        return $this->belongsTo(Cabana::class, 'cabana_id')->withTrashed();
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoCalendario::class, 'fuente_id');
    }
}
