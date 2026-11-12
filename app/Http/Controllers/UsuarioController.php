<?php

// Controlador HTTP de Usuario. Coordina solicitudes, servicios y respuestas; las rutas declaran los
// permisos de cada operación.

namespace App\Http\Controllers;

use App\Http\Requests\AsignarPermisosRequest;
use App\Http\Requests\AsignarRolesRequest;
use App\Http\Requests\ContrasenaUsuarioRequest;
use App\Http\Requests\EstadoUsuarioRequest;
use App\Http\Requests\GuardarUsuarioRequest;
use App\Http\Requests\PinUsuarioRequest;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ServicioAdministracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    // Recibe ServicioAdministracion mediante el contenedor de Laravel para reutilizarlas durante la
    // operación.
    public function __construct(private ServicioAdministracion $administracion) {}

    // Consulta y pagina los registros de Usuario para su listado; conserva el orden y los filtros
    // definidos en esta acción.
    public function listar(): View
    {
        return view('usuarios.listar', ['usuarios' => Usuario::with('roles')->orderBy('nombre')->orderBy('id')->paginate(20)]);
    }

    // Prepara el formulario de alta de Usuario con un modelo nuevo; mostrar el formulario todavía no
    // guarda un registro.
    public function crear(): View
    {
        return view('usuarios.formulario', ['usuario' => new Usuario]);
    }

    // Entrega los datos validados de Usuario al servicio responsable de guardarlos y devuelve la
    // respuesta de confirmación.
    public function guardar(GuardarUsuarioRequest $solicitud): RedirectResponse
    {
        $this->administracion->guardarUsuario($solicitud, $solicitud->validated());

        return $this->volver($solicitud);
    }

    // Carga el detalle de Usuario y sus relaciones necesarias para presentar la ficha.
    public function ver(Usuario $usuario): View
    {
        $usuario->load(['roles', 'permisos']);

        return view('usuarios.ver', [
            'usuario' => $usuario,
            'roles' => Rol::orderBy('nombre')->get(),
            'permisos' => Permiso::orderBy('codigo')->get(),
        ]);
    }

    // Presenta el formulario de Usuario con el registro resuelto por Laravel para su edición.
    public function editar(Usuario $usuario): View
    {
        return view('usuarios.formulario', compact('usuario'));
    }

    // Envía el registro de Usuario y los datos validados al servicio para aplicar una actualización
    // autorizada.
    public function actualizar(GuardarUsuarioRequest $solicitud, Usuario $usuario): RedirectResponse
    {
        $this->administracion->guardarUsuario($solicitud, $solicitud->validated(), $usuario);

        return $this->volver($solicitud);
    }

    // Envía el cambio de condición de Usuario al servicio mediante su operación autorizada.
    public function estado(EstadoUsuarioRequest $solicitud, Usuario $usuario): RedirectResponse
    {
        $this->administracion->cambiarEstado($solicitud, $usuario, $solicitud->boolean('activo'));

        return $this->volver($solicitud);
    }

    // Solicita retirar el bloqueo por intentos fallidos mediante el servicio administrativo.
    public function desbloquear(Request $solicitud, Usuario $usuario): RedirectResponse
    {
        $this->administracion->desbloquear($solicitud, $usuario);

        return $this->volver($solicitud);
    }

    // Restablece la contraseña por la operación administrativa específica, usando únicamente la
    // entrada validada.
    public function contrasena(ContrasenaUsuarioRequest $solicitud, Usuario $usuario): RedirectResponse
    {
        $this->administracion->cambiarContrasena($solicitud, $usuario, $solicitud->validated('contrasena'));

        return $this->volver($solicitud);
    }

    // Solicita restablecer o retirar el PIN con su permiso específico y devuelve una confirmación sin
    // exponer la credencial.
    public function pin(PinUsuarioRequest $solicitud, Usuario $usuario): RedirectResponse
    {
        $this->administracion->cambiarPin($solicitud, $usuario, $solicitud->boolean('retirar_pin') ? null : $solicitud->validated('pin'));

        return $this->volver($solicitud);
    }

    // Envía los identificadores validados al servicio que comprueba y reemplaza las asignaciones de
    // roles.
    public function roles(AsignarRolesRequest $solicitud, Usuario $usuario): RedirectResponse
    {
        $this->administracion->asignarRoles($solicitud, $usuario, $solicitud->validated('roles', []));

        return $this->volver($solicitud);
    }

    // Envía los permisos directos seleccionados al servicio; los permisos heredados por roles se
    // administran por separado.
    public function permisos(AsignarPermisosRequest $solicitud, Usuario $usuario): RedirectResponse
    {
        $this->administracion->asignarPermisos($solicitud, $usuario, $solicitud->validated('permisos', []));

        return $this->volver($solicitud);
    }

    // Delega la eliminación de Usuario al servicio, donde se verifican permisos y restricciones antes
    // de modificar los datos.
    public function eliminar(Request $solicitud, Usuario $usuario): RedirectResponse
    {
        $this->administracion->eliminarUsuario($solicitud, $usuario);

        return $this->volver($solicitud);
    }

    // Selecciona un destino que el usuario pueda consultar y agrega un mensaje de confirmación a la
    // siguiente respuesta.
    private function volver(Request $solicitud): RedirectResponse
    {
        return redirect()->route($solicitud->user()->can('usuarios.ver') ? 'usuarios.listar' : 'inicio')
            ->with('estado', 'Operación realizada correctamente.');
    }
}
