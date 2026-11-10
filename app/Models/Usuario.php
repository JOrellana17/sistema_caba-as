<?php

namespace App\Models;

use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['nombre', 'usuario', 'correo', 'contrasena'])]
#[Hidden(['contrasena', 'pin', 'remember_token'])]
class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasFactory;

    use SoftDeletes;

    /** Cantidad de intentos fallidos que bloquean la cuenta. */
    public const MAXIMO_INTENTOS = 3;

    protected $table = 'usuarios';

    protected $attributes = ['version_credenciales' => 0];

    protected $authPasswordName = 'contrasena';

    public function sesiones(): HasMany
    {
        return $this->hasMany(SesionUsuario::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_usuario', 'usuario_id', 'rol_id');
    }

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'permiso_usuario', 'usuario_id', 'permiso_id');
    }

    public function esAdministrador(): bool
    {
        return $this->activo && ! $this->trashed()
            && $this->roles()->where('codigo', 'administrador')->exists();
    }

    public function esAseador(): bool
    {
        return $this->roles()->where('codigo', 'aseador')->exists();
    }

    /**
     * Indica si la cuenta quedó bloqueada por intentos fallidos de acceso.
     */
    public function estaBloqueado(): bool
    {
        return $this->bloqueado_at !== null;
    }

    public function tienePermiso(string $codigo): bool
    {
        if (in_array($codigo, ['usuarios.eliminar', 'cabanas.eliminar', 'huespedes.eliminar', 'reservaciones.eliminar', 'sesiones.ver_todas'], true) && ! $this->esAdministrador()) {
            return false;
        }

        return $this->activo && ! $this->trashed() && (
            $this->permisos()->where('codigo', $codigo)->exists()
            || $this->roles()->whereHas('permisos', fn ($consulta) => $consulta->where('codigo', $codigo))->exists()
        );
    }

    /**
     * Convierte los atributos al tipo utilizado por el dominio.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'correo_verificado_at' => 'datetime',
            'contrasena' => 'hashed',
            'pin' => 'hashed',
            'version_credenciales' => 'integer',
            'activo' => 'boolean',
            'intentos_fallidos' => 'integer',
            'bloqueado_at' => 'datetime',
        ];
    }
}
