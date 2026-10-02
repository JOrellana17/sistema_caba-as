<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsignarPermisosRequest;
use App\Http\Requests\AsignarRolesRequest;
use App\Http\Requests\ContrasenaUsuarioRequest;
use App\Http\Requests\EstadoUsuarioRequest;
use App\Http\Requests\GuardarUsuarioRequest;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ServicioAdministracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function __construct(private ServicioAdministracion $administracion) {}

    public function listar(): View
    {
        return view('usuarios.listar', ['usuarios' => Usuario::with('roles')->orderBy('nombre')->orderBy('id')->paginate(20)]);
    }

    public function crear(): View
    {
        return view('usuarios.formulario', ['usuario' => new Usuario]);
    }

    public function guardar(GuardarUsuarioRequest $solicitud): RedirectResponse
    {
        $this->administracion->guardarUsuario($solicitud, $solicitud->validated());

        return $this->volver($solicitud);
    }

    public function ver(Usuario $usuario): View
    {
        $usuario->load(['roles', 'permisos']);

        return view('usuarios.ver', [
            'usuario' => $usuario,
            'roles' => Rol::orderBy('nombre')->get(),
            'permisos' => Permiso::orderBy('codigo')->get(),
        ]);
    }

    public function editar(Usuario $usuario): View
    {
        return view('usuarios.formulario', compact('usuario'));
    }

    public function actualizar(GuardarUsuarioRequest $solicitud, Usuario $usuario): RedirectResponse
    {
        $this->administracion->guardarUsuario($solicitud, $solicitud->validated(), $usuario);

        return $this->volver($solicitud);
    }

    public function estado(EstadoUsuarioRequest $solicitud, Usuario $usuario): RedirectResponse
    {
        $this->administracion->cambiarEstado($solicitud, $usuario, $solicitud->boolean('activo'));

        return $this->volver($solicitud);
    }

    public function contrasena(ContrasenaUsuarioRequest $solicitud, Usuario $usuario): RedirectResponse
    {
        $this->administracion->cambiarContrasena($solicitud, $usuario, $solicitud->validated('contrasena'));

        return $this->volver($solicitud);
    }

    public function roles(AsignarRolesRequest $solicitud, Usuario $usuario): RedirectResponse
    {
        $this->administracion->asignarRoles($solicitud, $usuario, $solicitud->validated('roles', []));

        return $this->volver($solicitud);
    }

    public function permisos(AsignarPermisosRequest $solicitud, Usuario $usuario): RedirectResponse
    {
        $this->administracion->asignarPermisos($solicitud, $usuario, $solicitud->validated('permisos', []));

        return $this->volver($solicitud);
    }

    public function eliminar(Request $solicitud, Usuario $usuario): RedirectResponse
    {
        $this->administracion->eliminarUsuario($solicitud, $usuario);

        return $this->volver($solicitud);
    }

    private function volver(Request $solicitud): RedirectResponse
    {
        return redirect()->route($solicitud->user()->can('usuarios.ver') ? 'usuarios.listar' : 'inicio')
            ->with('estado', 'Operación realizada correctamente.');
    }
}
