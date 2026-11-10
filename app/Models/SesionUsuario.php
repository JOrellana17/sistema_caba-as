<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SesionUsuario extends Model
{
    protected $table = 'sesiones_usuarios';

    protected $guarded = ['id'];

    protected $hidden = ['referencia'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class)->withTrashed();
    }

    protected function casts(): array
    {
        return ['iniciada_en' => 'datetime', 'actividad_en' => 'datetime', 'vence_en' => 'datetime', 'finalizada_en' => 'datetime'];
    }
}
