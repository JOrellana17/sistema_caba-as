<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['usuario_id', 'modulo', 'accion', 'direccion_ip', 'agente_usuario', 'registro_id', 'valores_anteriores', 'valores_nuevos'])]
class RegistroAuditoria extends Model
{
    protected $table = 'registros_auditoria';

    protected function casts(): array
    {
        return ['valores_anteriores' => 'array', 'valores_nuevos' => 'array'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class)->withTrashed();
    }
}
