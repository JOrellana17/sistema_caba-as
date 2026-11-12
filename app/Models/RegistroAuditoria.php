<?php

// Historial de cambios y accesos con actor y valores permitidos, sin secretos.

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Lista blanca de atributos asignables mediante fill/create; los campos sensibles se fijan explícitamente en los servicios.
#[Fillable(['usuario_id', 'modulo', 'accion', 'direccion_ip', 'agente_usuario', 'registro_id', 'valores_anteriores', 'valores_nuevos'])]
class RegistroAuditoria extends Model
{
    // Declara la tabla en español para no depender de la pluralización automática de Eloquent.
    protected $table = 'registros_auditoria';

    // Convierte los atributos persistidos a sus tipos PHP declarados para trabajar con fechas,
    // indicadores o datos estructurados de forma coherente.
    protected function casts(): array
    {
        return ['valores_anteriores' => 'array', 'valores_nuevos' => 'array'];
    }

    // Define la relación con el registro padre con Usuario para consultar y cargar sus registros
    // mediante Eloquent. Incluye registros eliminados lógicamente para conservar la consulta
    // histórica.
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class)->withTrashed();
    }
}
