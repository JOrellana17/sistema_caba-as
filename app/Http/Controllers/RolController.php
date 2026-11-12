<?php

// Controlador HTTP de Rol. Coordina solicitudes, servicios y respuestas; las rutas declaran los
// permisos de cada operación.

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
    // Recibe ServicioAdministracion mediante el contenedor de Laravel para reutilizarlas durante la
    // operación.
    public function __construct(private ServicioAdministracion $administracion) {}

    // Consulta y pagina los registros de Rol para su listado; conserva el orden y los filtros
    // definidos en esta acción.
    public function listar(): View
    {
        return view('roles.listar', ['roles' => Rol::withCount(['usuarios', 'permisos'])->orderBy('nombre')->paginate(20)]);
    }

    // Prepara el formulario de alta de Rol con un modelo nuevo; mostrar el formulario todavía no
    // guarda un registro.
    public function crear(): View
    {
        return view('roles.formulario', ['rol' => new Rol]);
    }

    // Entrega los datos validados de Rol al servicio responsable de guardarlos y devuelve la respuesta
    // de confirmación.
    public function guardar(GuardarRolRequest $solicitud): RedirectResponse
    {
        $this->administracion->guardarRol($solicitud, $solicitud->validated());

        return $this->volver($solicitud);
    }

    // Carga el detalle de Rol y sus relaciones necesarias para presentar la ficha.
    public function ver(Rol $rol): View
    {
        $rol->load('permisos');

        return view('roles.ver', ['rol' => $rol, 'permisos' => Permiso::orderBy('codigo')->get()]);
    }

    // Presenta el formulario de Rol con el registro resuelto por Laravel para su edición.
    public function editar(Rol $rol): View
    {
        return view('roles.formulario', compact('rol'));
    }

    // Envía el registro de Rol y los datos validados al servicio para aplicar una actualización
    // autorizada.
    public function actualizar(GuardarRolRequest $solicitud, Rol $rol): RedirectResponse
    {
        $this->administracion->guardarRol($solicitud, $solicitud->validated(), $rol);

        return $this->volver($solicitud);
    }

    // Solicita actualizar los permisos del rol mediante el servicio, que protege roles reservados y
    // evita elevar privilegios.
    public function permisos(AsignarPermisosRequest $solicitud, Rol $rol): RedirectResponse
    {
        $this->administracion->permisosRol($solicitud, $rol, $solicitud->validated('permisos', []));

        return $this->volver($solicitud);
    }

    // Delega la eliminación de Rol al servicio, donde se verifican permisos y restricciones antes de
    // modificar los datos.
    public function eliminar(Request $solicitud, Rol $rol): RedirectResponse
    {
        $this->administracion->eliminarRol($solicitud, $rol);

        return $this->volver($solicitud);
    }

    // Selecciona un destino que el usuario pueda consultar y agrega un mensaje de confirmación a la
    // siguiente respuesta.
    private function volver(Request $solicitud): RedirectResponse
    {
        return redirect()->route($solicitud->user()->can('roles.ver') ? 'roles.listar' : 'inicio')
            ->with('estado', 'Operación realizada correctamente.');
    }
}
