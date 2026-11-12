<?php

// Controlador HTTP de Permiso. Coordina solicitudes, servicios y respuestas; las rutas declaran los
// permisos de cada operación.

namespace App\Http\Controllers;

use App\Http\Requests\GuardarPermisoRequest;
use App\Models\Permiso;
use App\Services\ServicioAdministracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermisoController extends Controller
{
    // Recibe ServicioAdministracion mediante el contenedor de Laravel para reutilizarlas durante la
    // operación.
    public function __construct(private ServicioAdministracion $administracion) {}

    // Consulta y pagina los registros de Permiso para su listado; conserva el orden y los filtros
    // definidos en esta acción.
    public function listar(): View
    {
        return view('permisos.listar', ['permisos' => Permiso::orderBy('codigo')->paginate(25)]);
    }

    // Prepara el formulario de alta de Permiso con un modelo nuevo; mostrar el formulario todavía no
    // guarda un registro.
    public function crear(): View
    {
        return view('permisos.formulario', ['permiso' => new Permiso]);
    }

    // Entrega los datos validados de Permiso al servicio responsable de guardarlos y devuelve la
    // respuesta de confirmación.
    public function guardar(GuardarPermisoRequest $solicitud): RedirectResponse
    {
        $this->administracion->guardarPermiso($solicitud, $solicitud->validated());

        return $this->volver($solicitud);
    }

    // Presenta el formulario de Permiso con el registro resuelto por Laravel para su edición.
    public function editar(Permiso $permiso): View
    {
        return view('permisos.formulario', compact('permiso'));
    }

    // Envía el registro de Permiso y los datos validados al servicio para aplicar una actualización
    // autorizada.
    public function actualizar(GuardarPermisoRequest $solicitud, Permiso $permiso): RedirectResponse
    {
        $this->administracion->guardarPermiso($solicitud, $solicitud->validated(), $permiso);

        return $this->volver($solicitud);
    }

    // Delega la eliminación de Permiso al servicio, donde se verifican permisos y restricciones antes
    // de modificar los datos.
    public function eliminar(Request $solicitud, Permiso $permiso): RedirectResponse
    {
        $this->administracion->eliminarPermiso($solicitud, $permiso);

        return $this->volver($solicitud);
    }

    // Selecciona un destino que el usuario pueda consultar y agrega un mensaje de confirmación a la
    // siguiente respuesta.
    private function volver(Request $solicitud): RedirectResponse
    {
        return redirect()->route($solicitud->user()->can('permisos.ver') ? 'permisos.listar' : 'inicio')
            ->with('estado', 'Operación realizada correctamente.');
    }
}
