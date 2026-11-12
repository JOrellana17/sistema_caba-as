<?php

// Servicio de administracion. Centraliza las operaciones descritas en sus métodos para que los
// controladores reutilicen las mismas reglas.

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
    // Recibe ServicioAuditoria, ServicioSesiones mediante el contenedor de Laravel para reutilizarlas
    // durante la operación.
    public function __construct(private ServicioAuditoria $auditoria, private ServicioSesiones $sesiones) {}

    // Agrupa la operación y su auditoría en una transacción. Bloquea el rol administrador y relee al
    // actor para evitar cambios concurrentes de privilegios.
    private function ejecutar(Request $solicitud, string $permiso, Closure $operacion): mixed
    {
        // Las escrituras de este bloque se confirman juntas; una excepción revierte sus cambios en la base de datos.
        return DB::transaction(function () use ($solicitud, $permiso, $operacion) {
            // Un mismo bloqueo serializa las modificaciones de privilegios.
            // InnoDB conserva el bloqueo del rol hasta finalizar la transacción.
            Rol::where('codigo', 'administrador')->update(['updated_at' => now()]);
            // Consulta los privilegios actuales del actor antes de ejecutar una modificación.
            $actor = $solicitud->user()->fresh();
            abort_unless($actor, 403);
            Gate::forUser($actor)->authorize($permiso);

            return $operacion($actor);
        }, 3);
    }

    // Impide que un operador sin rol administrador modifique una cuenta que tiene ese rol, incluso si
    // la cuenta está inactiva.
    private function protegerCuenta(Usuario $actor, Usuario $usuario): void
    {
        $tieneRolAdministrador = $usuario->exists && $usuario->roles()->where('codigo', 'administrador')->exists();
        abort_if($tieneRolAdministrador && ! $actor->esAdministrador(), 403);
    }

    // Rechaza la operación si dejaría al sistema sin un administrador activo; la transacción permite
    // revertir el cambio.
    private function conservarAdministrador(): void
    {
        if (! Usuario::where('activo', true)->whereHas('roles', fn ($consulta) => $consulta->where('codigo', 'administrador'))->exists()) {
            throw ValidationException::withMessages(['administrador' => 'Debe permanecer al menos un administrador activo.']);
        }
    }

    // Comprueba que los permisos seleccionados existen y que el actor los posee; no permite conceder
    // facultades superiores a las propias.
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

    // Crea o actualiza datos de una cuenta con autorización y auditoría. Solo el alta admite un PIN
    // inicial; los roles se asignan por otra operación.
    public function guardarUsuario(Request $solicitud, array $datos, ?Usuario $usuario = null): Usuario
    {
        return $this->ejecutar($solicitud, $usuario ? 'usuarios.editar' : 'usuarios.crear', function (Usuario $actor) use ($solicitud, $datos, $usuario) {
            $nuevo = ! $usuario;
            $usuario = $usuario ? Usuario::findOrFail($usuario->id) : new Usuario;
            $this->protegerCuenta($actor, $usuario);
            // Conserva los valores previos para describir el cambio en la auditoría.
            $anteriores = $usuario->toArray();
            $usuario->fill($datos);
            if ($nuevo && ! empty($datos['pin'])) {
                $usuario->pin = $datos['pin'];
            }
            $usuario->save();
            $this->auditoria->registrarCambio($solicitud, 'usuarios', $nuevo ? 'creado' : 'actualizado', $usuario->id, $anteriores, $usuario->toArray());

            return $usuario;
        });
    }

    // Activa o desactiva la cuenta. Al desactivarla rota el recuerdo y revoca sesiones, preservando al
    // menos un administrador activo.
    public function cambiarEstado(Request $solicitud, Usuario $usuario, bool $activo): void
    {
        $this->ejecutar($solicitud, 'usuarios.cambiar_estado', function (Usuario $actor) use ($solicitud, $usuario, $activo) {
            $usuario->refresh();
            $this->protegerCuenta($actor, $usuario);
            $anterior = $usuario->activo;
            $usuario->activo = $activo;
            if (! $activo) {
                $usuario->remember_token = Str::random(60);
                $usuario->version_credenciales++;
                $this->sesiones->revocar($usuario, 'Cuenta desactivada');
            }
            $usuario->save();
            $this->conservarAdministrador();
            $this->auditoria->registrarCambio($solicitud, 'usuarios', 'estado_actualizado', $usuario->id, ['activo' => $anterior], ['activo' => $activo]);
        });
    }

    // Retira la fecha de bloqueo y reinicia los intentos fallidos, dejando constancia de quién
    // autorizó el desbloqueo.
    public function desbloquear(Request $solicitud, Usuario $usuario): void
    {
        $this->ejecutar($solicitud, 'usuarios.cambiar_estado', function (Usuario $actor) use ($solicitud, $usuario) {
            $usuario->refresh();
            $this->protegerCuenta($actor, $usuario);
            $anteriores = ['bloqueado_at' => $usuario->bloqueado_at, 'intentos_fallidos' => $usuario->intentos_fallidos];
            $usuario->bloqueado_at = null;
            $usuario->intentos_fallidos = 0;
            $usuario->save();
            $this->auditoria->registrarCambio($solicitud, 'usuarios', 'cuenta_desbloqueada', $usuario->id, $anteriores, ['bloqueado_at' => null, 'intentos_fallidos' => 0]);
        });
    }

    // Desactiva y elimina lógicamente una cuenta, revoca sus sesiones y conserva el historial y la
    // protección del último administrador.
    public function eliminarUsuario(Request $solicitud, Usuario $usuario): void
    {
        $this->ejecutar($solicitud, 'usuarios.eliminar', function (Usuario $actor) use ($solicitud, $usuario) {
            $usuario->refresh();
            $this->protegerCuenta($actor, $usuario);
            $usuario->activo = false;
            $usuario->version_credenciales++;
            $this->sesiones->revocar($usuario, 'Cuenta eliminada');
            $usuario->remember_token = Str::random(60);
            $usuario->save();
            $usuario->delete();
            $this->conservarAdministrador();
            $this->auditoria->registrarCambio($solicitud, 'usuarios', 'eliminado', $usuario->id);
        });
    }

    // Restablece la contraseña mediante su permiso específico. Retira el PIN anterior, revoca sesiones
    // y elimina el bloqueo de acceso.
    public function cambiarContrasena(Request $solicitud, Usuario $usuario, string $contrasena): void
    {
        $this->ejecutar($solicitud, 'usuarios.restablecer_contrasena', function (Usuario $actor) use ($solicitud, $usuario, $contrasena) {
            $usuario->refresh();
            $this->protegerCuenta($actor, $usuario);
            $usuario->contrasena = $contrasena;
            $usuario->pin = null;
            $usuario->version_credenciales++;
            $this->sesiones->revocar($usuario, 'Contraseña restablecida');
            $usuario->remember_token = Str::random(60);
            // Restablecer la contraseña también retira un bloqueo por intentos fallidos.
            $usuario->bloqueado_at = null;
            $usuario->intentos_fallidos = 0;
            $usuario->save();
            $this->auditoria->registrarCambio($solicitud, 'usuarios', 'contrasena_restablecida', $usuario->id);
        });
    }

    // Restablece o retira el PIN autorizado. Cambia la versión de credenciales y revoca las sesiones
    // sin guardar secretos en la auditoría.
    public function cambiarPin(Request $solicitud, Usuario $usuario, ?string $pin): void
    {
        $this->ejecutar($solicitud, 'usuarios.restablecer_pin', function (Usuario $actor) use ($solicitud, $usuario, $pin) {
            $usuario->refresh();
            $this->protegerCuenta($actor, $usuario);
            $usuario->pin = $pin;
            $usuario->remember_token = Str::random(60);
            $usuario->version_credenciales++;
            $usuario->save();
            $this->sesiones->revocar($usuario, 'PIN restablecido');
            $this->auditoria->registrarCambio($solicitud, 'usuarios', 'pin_restablecido', $usuario->id);
        });
    }

    // Reemplaza los roles seleccionados comprobando permisos, protección del administrador y
    // exclusividad del rol Aseador.
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
            if ($seleccion->contains('codigo', 'aseador') && ($seleccion->count() !== 1 || $usuario->permisos()->exists())) {
                throw ValidationException::withMessages(['roles' => 'Aseador es un rol exclusivo. Retira primero los otros roles y permisos directos.']);
            }
            $anteriores = $usuario->roles()->pluck('roles.id')->all();
            $usuario->roles()->sync($roles);
            $this->conservarAdministrador();
            $this->auditoria->registrarCambio($solicitud, 'usuarios', 'roles_asignados', $usuario->id, ['roles' => $anteriores], ['roles' => array_map('intval', $roles)]);
        });
    }

    // Reemplaza los permisos directos sin alterar los heredados por roles. Impide asignaciones
    // incompatibles con el rol Aseador.
    public function asignarPermisos(Request $solicitud, Usuario $usuario, array $permisos): void
    {
        $this->ejecutar($solicitud, 'usuarios.gestionar_permisos', function (Usuario $actor) use ($solicitud, $usuario, $permisos) {
            $usuario->refresh();
            $this->protegerCuenta($actor, $usuario);
            $this->comprobarPermisos($actor, $permisos);
            if ($usuario->esAseador() && $permisos !== []) {
                throw ValidationException::withMessages(['permisos' => 'Una cuenta Aseador solo utiliza los permisos de su rol.']);
            }
            $anteriores = $usuario->permisos()->pluck('permisos.id')->all();
            $usuario->permisos()->sync($permisos);
            $this->auditoria->registrarCambio($solicitud, 'usuarios', 'permisos_asignados', $usuario->id, ['permisos' => $anteriores], ['permisos' => array_map('intval', $permisos)]);
        });
    }

    // Crea o edita un rol ordinario; los códigos de Administrador y Aseador están reservados y
    // protegidos.
    public function guardarRol(Request $solicitud, array $datos, ?Rol $rol = null): Rol
    {
        return $this->ejecutar($solicitud, $rol ? 'roles.editar' : 'roles.crear', function () use ($solicitud, $datos, $rol) {
            $nuevo = ! $rol;
            $rol = $rol ? Rol::findOrFail($rol->id) : new Rol;
            if ($rol->codigo === 'administrador' || $datos['codigo'] === 'administrador') {
                throw ValidationException::withMessages(['codigo' => 'El rol Administrador está protegido.']);
            }
            if ($rol->codigo === 'aseador' || $datos['codigo'] === 'aseador') {
                throw ValidationException::withMessages(['codigo' => 'El rol Aseador está protegido.']);
            }
            // Conserva los valores previos para describir el cambio en la auditoría.
            $anteriores = $rol->toArray();
            $rol->fill($datos)->save();
            $this->auditoria->registrarCambio($solicitud, 'roles', $nuevo ? 'creado' : 'actualizado', $rol->id, $anteriores, $rol->toArray());

            return $rol;
        });
    }

    // Elimina físicamente un rol ordinario solamente si no está asignado a ninguna cuenta, incluidas
    // las eliminadas lógicamente.
    public function eliminarRol(Request $solicitud, Rol $rol): void
    {
        $this->ejecutar($solicitud, 'roles.eliminar', function () use ($solicitud, $rol) {
            $rol->refresh();
            if (in_array($rol->codigo, ['administrador', 'aseador'], true) || $rol->usuarios()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['rol' => 'No se puede eliminar un rol protegido o asignado a usuarios.']);
            }
            $this->auditoria->registrarCambio($solicitud, 'roles', 'eliminado', $rol->id, $rol->toArray());
            $rol->delete();
        });
    }

    // Actualiza los permisos de un rol ordinario sin permitir que el actor conceda o retire facultades
    // que no posee.
    public function permisosRol(Request $solicitud, Rol $rol, array $permisos): void
    {
        $this->ejecutar($solicitud, 'roles.gestionar_permisos', function (Usuario $actor) use ($solicitud, $rol, $permisos) {
            $rol->refresh();
            if ($rol->codigo === 'administrador') {
                throw ValidationException::withMessages(['rol' => 'Los permisos del rol Administrador están protegidos.']);
            }
            if ($rol->codigo === 'aseador') {
                throw ValidationException::withMessages(['rol' => 'Los permisos del rol Aseador están protegidos.']);
            }
            $this->comprobarPermisos($actor, $permisos);
            // Tampoco permite retirar facultades superiores mediante la edición de un rol.
            $anteriores = $rol->permisos()->pluck('permisos.id')->all();
            $this->comprobarPermisos($actor, $anteriores);
            $rol->permisos()->sync($permisos);
            $this->auditoria->registrarCambio($solicitud, 'roles', 'permisos_asignados', $rol->id, ['permisos' => $anteriores], ['permisos' => array_map('intval', $permisos)]);
        });
    }

    // Guarda un código y su descripción. Protege códigos en uso y asocia los nuevos al administrador;
    // crear un código no implementa una función.
    public function guardarPermiso(Request $solicitud, array $datos, ?Permiso $permiso = null): Permiso
    {
        return $this->ejecutar($solicitud, $permiso ? 'permisos.editar' : 'permisos.crear', function () use ($solicitud, $datos, $permiso) {
            $nuevo = ! $permiso;
            $permiso = $permiso ? Permiso::findOrFail($permiso->id) : new Permiso;
            if ($permiso->exists && $datos['codigo'] !== $permiso->codigo && ($permiso->protegido || $permiso->roles()->where('codigo', '!=', 'administrador')->exists() || $permiso->usuarios()->withTrashed()->exists())) {
                throw ValidationException::withMessages(['codigo' => 'No se puede renombrar un permiso del sistema o en uso.']);
            }
            // Conserva los valores previos para describir el cambio en la auditoría.
            $anteriores = $permiso->toArray();
            $permiso->fill($datos)->save();
            if ($nuevo) {
                Rol::where('codigo', 'administrador')->firstOrFail()->permisos()->syncWithoutDetaching([$permiso->id]);
            }
            $this->auditoria->registrarCambio($solicitud, 'permisos', $nuevo ? 'creado' : 'actualizado', $permiso->id, $anteriores, $permiso->toArray());

            return $permiso;
        });
    }

    // Retira un permiso no protegido ni asignado al personal; elimina sus asociaciones al rol
    // administrador y conserva la auditoría.
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
