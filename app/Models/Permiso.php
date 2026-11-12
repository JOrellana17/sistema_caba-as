<?php

// Código de autorización asociado directamente al usuario o mediante sus roles.

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Lista blanca de atributos asignables mediante fill/create; los campos sensibles se fijan explícitamente en los servicios.
#[Fillable(['codigo', 'descripcion'])]
class Permiso extends Model
{
    // Declara la tabla en español para no depender de la pluralización automática de Eloquent.
    protected $table = 'permisos';

    // Convierte los atributos persistidos a sus tipos PHP declarados para trabajar con fechas,
    // indicadores o datos estructurados de forma coherente.
    protected function casts(): array
    {
        return ['protegido' => 'boolean'];
    }

    // Define la relación muchos a muchos con Rol para consultar y cargar sus registros mediante
    // Eloquent. Usa la tabla pivote permiso_rol.
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'permiso_rol', 'permiso_id', 'rol_id');
    }

    // Define la relación muchos a muchos con Usuario para consultar y cargar sus registros mediante
    // Eloquent. Usa la tabla pivote permiso_usuario.
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'permiso_usuario', 'permiso_id', 'usuario_id');
    }
}
