<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarPermisoRequest;
use App\Models\Permiso;
use App\Services\ServicioAdministracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermisoController extends Controller
{
    public function __construct(private ServicioAdministracion $administracion) {}

    public function listar(): View
    {
        return view('permisos.listar', ['permisos' => Permiso::orderBy('codigo')->paginate(25)]);
    }

    public function crear(): View
    {
        return view('permisos.formulario', ['permiso' => new Permiso]);
    }

    public function guardar(GuardarPermisoRequest $solicitud): RedirectResponse
    {
        $this->administracion->guardarPermiso($solicitud, $solicitud->validated());

        return $this->volver($solicitud);
    }

    public function editar(Permiso $permiso): View
    {
        return view('permisos.formulario', compact('permiso'));
    }

    public function actualizar(GuardarPermisoRequest $solicitud, Permiso $permiso): RedirectResponse
    {
        $this->administracion->guardarPermiso($solicitud, $solicitud->validated(), $permiso);

        return $this->volver($solicitud);
    }

    public function eliminar(Request $solicitud, Permiso $permiso): RedirectResponse
    {
        $this->administracion->eliminarPermiso($solicitud, $permiso);

        return $this->volver($solicitud);
    }

    private function volver(Request $solicitud): RedirectResponse
    {
        return redirect()->route($solicitud->user()->can('permisos.ver') ? 'permisos.listar' : 'inicio')
            ->with('estado', 'Operación realizada correctamente.');
    }
}
