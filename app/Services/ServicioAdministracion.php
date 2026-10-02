<?php

namespace App\Services;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServicioAdministracion
{
    public function __construct(private ServicioAuditoria $auditoria) {}

    private function ejecutar(Request $solicitud, string $permiso, Closure $operacion): mixed
    {
        return DB::transaction(function () use ($solicitud, $permiso, $operacion) {
            // Un mismo bloqueo serializa las modificaciones de privilegios.
            // InnoDB conserva el bloqueo del rol hasta finalizar la transacción.
            Rol::where('codigo', 'administrador')->update(['updated_at' => now()]);
            $actor = $solicitud->user()->fresh();
            abort_unless($actor, 403);
            Gate::forUser($actor)->authorize($permiso);

            return $operacion($actor);
        }, 3);
    }

    private function protegerCuenta(Usuario $actor, Usuario $usuario): void
    {
        $tieneRolAdministrador = $usuario->exists && $usuario->roles()->where('codigo', 'administrador')->exists();
        abort_if($tieneRolAdministrador && ! $actor->esAdministrador(), 403);
    }

    private function conservarAdministrador(): void
    {
        if (! Usuario::where('activo', true)->whereHas('roles', fn ($consulta) => $consulta->where('codigo', 'administrador'))->exists()) {
            throw ValidationException::withMessages(['administrador' => 'Debe permanecer al menos un administrador activo.']);
        }
    }

    private function comprobarPermisos(Usuario $actor, array $identificadores): void
    {
        $permisos = Permiso::whereIn('id', $identificadores)->get();
        if ($permisos->count() !== count(array_unique($identificadores))) {
            throw ValidationException::withMessages(['permisos' => 'La selección de permisos ya no es válida.']);
        }
        foreach ($permisos as $permiso) {
            abort_unless($actor->tienePermiso($permiso->codigo), 403, 'No puedes conceder permisos que no posees.');
        }
    }

    public function guardarUsuario(Request $solicitud, array $datos, ?Usuario $usuario = null): Usuario
    {
        return $this->ejecutar($solicitud, $usuario ? 'usuarios.editar' : 'usuarios.crear', function (Usuario $actor) use ($solicitud, $datos, $usuario) {
            $nuevo = ! $usuario;
            $usuario = $usuario ? Usuario::findOrFail($usuario->id) : new Usuario;
            $this->protegerCuenta($actor, $usuario);
            $anteriores = $usuario->toArray();
            $usuario->fill($datos);
            $usuario->save();
            $this->auditoria->registrarCambio($solicitud, 'usuarios', $nuevo ? 'creado' : 'actualizado', $usuario->id, $anteriores, $usuario->toArray());

            return $usuario;
        });
    }

    public function cambiarEstado(Request $solicitud, Usuario $usuario, bool $activo): void
    {
        $this->ejecutar($solicitud, 'usuarios.cambiar_estado', function (Usuario $actor) use ($solicitud, $usuario, $activo) {
            $usuario->refresh();
            $this->protegerCuenta($actor, $usuario);
            $anterior = $usuario->activo;
            $usuario->activo = $activo;
            if (! $activo) {
                $usuario->remember_token = Str::random(60);
            }
            $usuario->save();
            $this->conservarAdministrador();
            $this->auditoria->registrarCambio($solicitud, 'usuarios', 'estado_actualizado', $usuario->id, ['activo' => $anterior], ['activo' => $activo]);
        });
    }

    public function eliminarUsuario(Request $solicitud, Usuario $usuario): void
    {
        $this->ejecutar($solicitud, 'usuarios.eliminar', function (Usuario $actor) use ($solicitud, $usuario) {
            $usuario->refresh();
            $this->protegerCuenta($actor, $usuario);
            $usuario->activo = false;
            $usuario->remember_token = Str::random(60);
            $usuario->save();
            $usuario->delete();
            $this->conservarAdministrador();
            $this->auditoria->registrarCambio($solicitud, 'usuarios', 'eliminado', $usuario->id);
        });
    }

    public function cambiarContrasena(Request $solicitud, Usuario $usuario, string $contrasena): void
    {
        $this->ejecutar($solicitud, 'usuarios.restablecer_contrasena', function (Usuario $actor) use ($solicitud, $usuario, $contrasena) {
            $usuario->refresh();
            $this->protegerCuenta($actor, $usuario);
            $usuario->contrasena = $contrasena;
            $usuario->remember_token = Str::random(60);
            $usuario->save();
            $this->auditoria->registrarCambio($solicitud, 'usuarios', 'contrasena_restablecida', $usuario->id);
        });
    }

    public function asignarRoles(Request $solicitud, Usuario $usuario, array $roles): void
    {
        $this->ejecutar($solicitud, 'usuarios.gestionar_roles', function (Usuario $actor) use ($solicitud, $usuario, $roles) {
            $usuario->refresh();
            $this->protegerCuenta($actor, $usuario);
            $seleccion = Rol::with('permisos')->whereIn('id', $roles)->get();
            if ($seleccion->count() !== count(array_unique($roles))) {
                throw ValidationException::withMessages(['roles' => 'La selección de roles ya no es válida.']);
            }
            foreach ($seleccion as $rol) {
                abort_if($rol->codigo === 'administrador' && ! $actor->esAdministrador(), 403);
                $this->comprobarPermisos($actor, $rol->permisos->modelKeys());
            }
            $anteriores = $usuario->roles()->pluck('roles.id')->all();
            $usuario->roles()->sync($roles);
            $this->conservarAdministrador();
            $this->auditoria->registrarCambio($solicitud, 'usuarios', 'roles_asignados', $usuario->id, ['roles' => $anteriores], ['roles' => array_map('intval', $roles)]);
        });
    }

    public function asignarPermisos(Request $solicitud, Usuario $usuario, array $permisos): void
    {
        $this->ejecutar($solicitud, 'usuarios.gestionar_permisos', function (Usuario $actor) use ($solicitud, $usuario, $permisos) {
            $usuario->refresh();
            $this->protegerCuenta($actor, $usuario);
            $this->comprobarPermisos($actor, $permisos);
            $anteriores = $usuario->permisos()->pluck('permisos.id')->all();
            $usuario->permisos()->sync($permisos);
            $this->auditoria->registrarCambio($solicitud, 'usuarios', 'permisos_asignados', $usuario->id, ['permisos' => $anteriores], ['permisos' => array_map('intval', $permisos)]);
        });
    }

    public function guardarRol(Request $solicitud, array $datos, ?Rol $rol = null): Rol
    {
        return $this->ejecutar($solicitud, $rol ? 'roles.editar' : 'roles.crear', function () use ($solicitud, $datos, $rol) {
            $nuevo = ! $rol;
            $rol = $rol ? Rol::findOrFail($rol->id) : new Rol;
            if ($rol->codigo === 'administrador' || $datos['codigo'] === 'administrador') {
                throw ValidationException::withMessages(['codigo' => 'El rol Administrador está protegido.']);
            }
            $anteriores = $rol->toArray();
            $rol->fill($datos)->save();
            $this->auditoria->registrarCambio($solicitud, 'roles', $nuevo ? 'creado' : 'actualizado', $rol->id, $anteriores, $rol->toArray());

            return $rol;
        });
    }

    public function eliminarRol(Request $solicitud, Rol $rol): void
    {
        $this->ejecutar($solicitud, 'roles.eliminar', function () use ($solicitud, $rol) {
            $rol->refresh();
            if ($rol->codigo === 'administrador' || $rol->usuarios()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['rol' => 'No se puede eliminar un rol protegido o asignado a usuarios.']);
            }
            $this->auditoria->registrarCambio($solicitud, 'roles', 'eliminado', $rol->id, $rol->toArray());
            $rol->delete();
        });
    }

    public function permisosRol(Request $solicitud, Rol $rol, array $permisos): void
    {
        $this->ejecutar($solicitud, 'roles.gestionar_permisos', function (Usuario $actor) use ($solicitud, $rol, $permisos) {
            $rol->refresh();
            if ($rol->codigo === 'administrador') {
                throw ValidationException::withMessages(['rol' => 'Los permisos del rol Administrador están protegidos.']);
            }
            $this->comprobarPermisos($actor, $permisos);
            // Tampoco permite retirar facultades superiores mediante la edición de un rol.
            $anteriores = $rol->permisos()->pluck('permisos.id')->all();
            $this->comprobarPermisos($actor, $anteriores);
            $rol->permisos()->sync($permisos);
            $this->auditoria->registrarCambio($solicitud, 'roles', 'permisos_asignados', $rol->id, ['permisos' => $anteriores], ['permisos' => array_map('intval', $permisos)]);
        });
    }

    public function guardarPermiso(Request $solicitud, array $datos, ?Permiso $permiso = null): Permiso
    {
        return $this->ejecutar($solicitud, $permiso ? 'permisos.editar' : 'permisos.crear', function () use ($solicitud, $datos, $permiso) {
            $nuevo = ! $permiso;
            $permiso = $permiso ? Permiso::findOrFail($permiso->id) : new Permiso;
            if ($permiso->exists && $datos['codigo'] !== $permiso->codigo && ($permiso->protegido || $permiso->roles()->where('codigo', '!=', 'administrador')->exists() || $permiso->usuarios()->withTrashed()->exists())) {
                throw ValidationException::withMessages(['codigo' => 'No se puede renombrar un permiso del sistema o en uso.']);
            }
            $anteriores = $permiso->toArray();
            $permiso->fill($datos)->save();
            if ($nuevo) {
                Rol::where('codigo', 'administrador')->firstOrFail()->permisos()->syncWithoutDetaching([$permiso->id]);
            }
            $this->auditoria->registrarCambio($solicitud, 'permisos', $nuevo ? 'creado' : 'actualizado', $permiso->id, $anteriores, $permiso->toArray());

            return $permiso;
        });
    }

    public function eliminarPermiso(Request $solicitud, Permiso $permiso): void
    {
        $this->ejecutar($solicitud, 'permisos.eliminar', function () use ($solicitud, $permiso) {
            $permiso->refresh();
            if ($permiso->protegido || $permiso->roles()->where('codigo', '!=', 'administrador')->exists() || $permiso->usuarios()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['permiso' => 'No se puede eliminar un permiso del sistema o asignado al personal.']);
            }
            $this->auditoria->registrarCambio($solicitud, 'permisos', 'eliminado', $permiso->id, $permiso->toArray());
            $permiso->roles()->detach();
            $permiso->delete();
        });
    }
}
