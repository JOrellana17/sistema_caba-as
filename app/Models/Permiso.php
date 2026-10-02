<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['codigo', 'descripcion'])]
class Permiso extends Model
{
    protected $table = 'permisos';

    protected function casts(): array
    {
        return ['protegido' => 'boolean'];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'permiso_rol', 'permiso_id', 'rol_id');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'permiso_usuario', 'permiso_id', 'usuario_id');
    }
}
