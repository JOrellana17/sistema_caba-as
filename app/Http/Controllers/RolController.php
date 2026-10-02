<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsignarPermisosRequest;
use App\Http\Requests\GuardarRolRequest;
use App\Models\Permiso;
use App\Models\Rol;
use App\Services\ServicioAdministracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolController extends Controller
{
    public function __construct(private ServicioAdministracion $administracion) {}

    public function listar(): View
    {
        return view('roles.listar', ['roles' => Rol::withCount(['usuarios', 'permisos'])->orderBy('nombre')->paginate(20)]);
    }

    public function crear(): View
    {
        return view('roles.formulario', ['rol' => new Rol]);
    }

    public function guardar(GuardarRolRequest $solicitud): RedirectResponse
    {
        $this->administracion->guardarRol($solicitud, $solicitud->validated());

        return $this->volver($solicitud);
    }

    public function ver(Rol $rol): View
    {
        $rol->load('permisos');

        return view('roles.ver', ['rol' => $rol, 'permisos' => Permiso::orderBy('codigo')->get()]);
    }

    public function editar(Rol $rol): View
    {
        return view('roles.formulario', compact('rol'));
    }

    public function actualizar(GuardarRolRequest $solicitud, Rol $rol): RedirectResponse
    {
        $this->administracion->guardarRol($solicitud, $solicitud->validated(), $rol);

        return $this->volver($solicitud);
    }

    public function permisos(AsignarPermisosRequest $solicitud, Rol $rol): RedirectResponse
    {
        $this->administracion->permisosRol($solicitud, $rol, $solicitud->validated('permisos', []));

        return $this->volver($solicitud);
    }

    public function eliminar(Request $solicitud, Rol $rol): RedirectResponse
    {
        $this->administracion->eliminarRol($solicitud, $rol);

        return $this->volver($solicitud);
    }

    private function volver(Request $solicitud): RedirectResponse
    {
        return redirect()->route($solicitud->user()->can('roles.ver') ? 'roles.listar' : 'inicio')
            ->with('estado', 'Operación realizada correctamente.');
    }
}
