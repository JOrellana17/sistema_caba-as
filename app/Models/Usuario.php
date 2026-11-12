<?php

// Cuenta autenticable del personal: roles, permisos, credenciales con hash y eliminación lógica.

namespace App\Models;

use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

// Lista blanca de atributos asignables mediante fill/create; los campos sensibles se fijan explícitamente en los servicios.
#[Fillable(['nombre', 'usuario', 'correo', 'contrasena'])]
// Oculta estos atributos en arrays y JSON; no sustituye la autorización para acceder al modelo.
#[Hidden(['contrasena', 'pin', 'remember_token'])]
class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasFactory;

    // Las eliminaciones fijan deleted_at; las consultas normales excluyen esas filas y restore() permite recuperarlas.
    use SoftDeletes;

    /** Cantidad de intentos fallidos que bloquean la cuenta. */
    public const MAXIMO_INTENTOS = 3;

    // Declara la tabla en español para no depender de la pluralización automática de Eloquent.
    protected $table = 'usuarios';

    protected $attributes = ['version_credenciales' => 0];

    protected $authPasswordName = 'contrasena';

    // Define la relación uno a muchos con SesionUsuario para consultar y cargar sus registros mediante
    // Eloquent.
    public function sesiones(): HasMany
    {
        return $this->hasMany(SesionUsuario::class);
    }

    // Define la relación muchos a muchos con Rol para consultar y cargar sus registros mediante
    // Eloquent. Usa la tabla pivote rol_usuario.
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_usuario', 'usuario_id', 'rol_id');
    }

    // Define la relación muchos a muchos con Permiso para consultar y cargar sus registros mediante
    // Eloquent. Usa la tabla pivote permiso_usuario.
    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'permiso_usuario', 'usuario_id', 'permiso_id');
    }

    // Comprueba que la cuenta esté activa, no eliminada y vinculada al rol administrador; tener
    // permisos directos no equivale a poseer ese rol.
    public function esAdministrador(): bool
    {
        return $this->activo && ! $this->trashed()
            && $this->roles()->where('codigo', 'administrador')->exists();
    }

    // Consulta el rol Aseador para dirigir su navegación al módulo operativo correspondiente.
    public function esAseador(): bool
    {
        return $this->roles()->where('codigo', 'aseador')->exists();
    }

    // Indica si la fecha de bloqueo está registrada; el desbloqueo es una operación administrativa
    // explícita.
    /**
     * Indica si la cuenta quedó bloqueada por intentos fallidos de acceso.
     */
    public function estaBloqueado(): bool
    {
        return $this->bloqueado_at !== null;
    }

    // Combina permisos directos y heredados por roles. La eliminación lógica y el historial global
    // exigen además rol administrador, aunque exista un permiso directo.
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

    // Convierte los atributos persistidos a sus tipos PHP declarados para trabajar con fechas,
    // indicadores o datos estructurados de forma coherente. Las credenciales se transforman a hash al
    // asignarlas.
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
