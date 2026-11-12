<?php

// Agrupación de permisos asociada a usuarios mediante una tabla pivote.

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Lista blanca de atributos asignables mediante fill/create; los campos sensibles se fijan explícitamente en los servicios.
#[Fillable(['nombre', 'codigo', 'descripcion'])]
class Rol extends Model
{
    // Declara la tabla en español para no depender de la pluralización automática de Eloquent.
    protected $table = 'roles';

    // Define la relación muchos a muchos con Permiso para consultar y cargar sus registros mediante
    // Eloquent. Usa la tabla pivote permiso_rol.
    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'permiso_rol', 'rol_id', 'permiso_id');
    }

    // Define la relación muchos a muchos con Usuario para consultar y cargar sus registros mediante
    // Eloquent. Usa la tabla pivote rol_usuario.
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'rol_usuario', 'rol_id', 'usuario_id');
    }
}
