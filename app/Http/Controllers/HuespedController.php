<?php

// Controlador HTTP de Huesped. Coordina solicitudes, servicios y respuestas; las rutas declaran los
// permisos de cada operación.

namespace App\Http\Controllers;

use App\Http\Requests\BuscarHuespedesRequest;
use App\Http\Requests\CondicionHuespedRequest;
use App\Http\Requests\ConsultarHuespedesRequest;
use App\Http\Requests\GuardarHuespedRequest;
use App\Models\Huesped;
use App\Models\RegistroAuditoria;
use App\Services\ServicioBusquedaHuespedes;
use App\Services\ServicioHuespedes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class HuespedController extends Controller
{
    // Recibe ServicioHuespedes, ServicioBusquedaHuespedes mediante el contenedor de Laravel para
    // reutilizarlas durante la operación.
    public function __construct(private ServicioHuespedes $huespedes, private ServicioBusquedaHuespedes $busqueda) {}

    // Consulta y pagina los registros de Huesped para su listado; conserva el orden y los filtros
    // definidos en esta acción.
    public function listar(ConsultarHuespedesRequest $solicitud): View
    {
        // Usa solo los filtros aprobados por el Form Request, no todos los parámetros recibidos.
        $filtros = $solicitud->validated();
        $eliminados = $solicitud->boolean('eliminados');
        if ($eliminados) {
            Gate::authorize('huespedes.restaurar');
        }
        $consulta = Huesped::query();
        if ($eliminados) {
            $consulta->onlyTrashed();
        }
        foreach (['activo', 'lista_negra', 'frecuente', 'vip'] as $campo) {
            if ($solicitud->filled($campo)) {
                $consulta->where($campo, $solicitud->boolean($campo));
            }
        }
        if ($solicitud->filled('buscar')) {
            Gate::authorize('huespedes.buscar');
            $this->busqueda->filtrar($consulta, $filtros['buscar']);
        } else {
            $consulta->orderBy('nombre_busqueda')->orderBy('id');
        }

        return view('huespedes.listar', [
            'huespedes' => $consulta->paginate(20)->withQueryString(),
            'filtros' => $filtros,
            'eliminados' => $eliminados,
            'puedeEditar' => $solicitud->user()->can('huespedes.editar'),
        ]);
    }

    // Presenta la pantalla de búsqueda compartida sin crear ni seleccionar automáticamente un huésped.
    public function buscador(): View
    {
        return view('huespedes.buscador');
    }

    // Entrega coincidencias en JSON mediante el servicio compartido, limitando los datos de identidad
    // según los permisos.
    public function buscar(BuscarHuespedesRequest $solicitud): JsonResponse
    {
        // El negocio autoriza identidad completa a quienes pueden buscar.
        return response()->json($this->busqueda->buscar($solicitud->validated('termino_busqueda'), true))
            ->header('Cache-Control', 'private, no-store');
    }

    // Prepara el formulario de alta de Huesped con un modelo nuevo; mostrar el formulario todavía no
    // guarda un registro.
    public function crear(): View
    {
        return view('huespedes.formulario', ['huesped' => new Huesped]);
    }

    // Entrega los datos validados de Huesped al servicio responsable de guardarlos y devuelve la
    // respuesta de confirmación.
    public function guardar(GuardarHuespedRequest $solicitud): RedirectResponse
    {
        $huesped = $this->huespedes->guardar($solicitud, $solicitud->validated());

        return $this->volver($solicitud, $huesped);
    }

    // Crea un huésped desde el formulario integrado y devuelve su resumen en JSON para reutilizar el
    // mismo identificador sin perder los demás campos.
    public function guardarDesdeFormulario(GuardarHuespedRequest $solicitud): JsonResponse
    {
        $huesped = $this->huespedes->guardar($solicitud, $solicitud->validated());

        return response()->json(['huesped' => $this->busqueda->resumir($huesped, true)], 201)
            ->header('Cache-Control', 'private, no-store');
    }

    // Carga el detalle de Huesped y sus relaciones necesarias para presentar la ficha.
    public function ver(Request $solicitud, Huesped $huesped): View
    {
        $historial = $solicitud->user()->can('auditoria.ver')
            ? RegistroAuditoria::with('usuario:id,nombre,deleted_at')->where('modulo', 'huespedes')
                ->where('registro_id', $huesped->id)->latest('id')->paginate(20)
            : null;

        $estancias = $solicitud->user()->can('reservaciones.ver')
            ? $huesped->reservaciones()->with('cabana:id,nombre,deleted_at')
                ->orderByDesc('fecha_entrada')->orderByDesc('id')->paginate(10, ['*'], 'pagina_estancias')->withQueryString()
            : null;

        return view('huespedes.ver', compact('huesped', 'historial', 'estancias'));
    }

    // Presenta el formulario de Huesped con el registro resuelto por Laravel para su edición.
    public function editar(Huesped $huesped): View
    {
        return view('huespedes.formulario', compact('huesped'));
    }

    // Envía el registro de Huesped y los datos validados al servicio para aplicar una actualización
    // autorizada.
    public function actualizar(GuardarHuespedRequest $solicitud, Huesped $huesped): RedirectResponse
    {
        $this->huespedes->guardar($solicitud, $solicitud->validated(), $huesped);

        return $this->volver($solicitud, $huesped);
    }

    // Envía el cambio de condición de Huesped al servicio mediante su operación autorizada.
    public function estado(CondicionHuespedRequest $solicitud, Huesped $huesped): RedirectResponse
    {
        $this->huespedes->cambiarCondicion($solicitud, $huesped, 'activo', $solicitud->boolean('valor'));

        return $this->volver($solicitud, $huesped);
    }

    // Solicita cambiar la marca de lista negra mediante el permiso específico, conservando la ficha y
    // sus relaciones.
    public function listaNegra(CondicionHuespedRequest $solicitud, Huesped $huesped): RedirectResponse
    {
        $this->huespedes->cambiarCondicion($solicitud, $huesped, 'lista_negra', $solicitud->boolean('valor'));

        return $this->volver($solicitud, $huesped);
    }

    // Solicita cambiar la marca de cliente frecuente con autorización y auditoría.
    public function frecuente(CondicionHuespedRequest $solicitud, Huesped $huesped): RedirectResponse
    {
        $this->huespedes->cambiarCondicion($solicitud, $huesped, 'frecuente', $solicitud->boolean('valor'));

        return $this->volver($solicitud, $huesped);
    }

    // Delega la eliminación de Huesped al servicio, donde se verifican permisos y restricciones antes
    // de modificar los datos.
    public function eliminar(Request $solicitud, Huesped $huesped): RedirectResponse
    {
        $this->huespedes->eliminar($solicitud, $huesped);

        return $this->volver($solicitud);
    }

    // Solicita restaurar Huesped mediante el servicio y devuelve confirmación al destino permitido.
    public function restaurar(Request $solicitud, Huesped $huesped): RedirectResponse
    {
        $this->huespedes->restaurar($solicitud, $huesped);

        return $this->volver($solicitud, $huesped);
    }

    // Selecciona un destino que el usuario pueda consultar y agrega un mensaje de confirmación a la
    // siguiente respuesta.
    private function volver(Request $solicitud, ?Huesped $huesped = null): RedirectResponse
    {
        $destino = $solicitud->user()->can('huespedes.ver')
            ? ($huesped ? route('huespedes.ver', $huesped) : route('huespedes.listar'))
            : route('inicio');

        return redirect($destino)->with('estado', 'Operación realizada correctamente.');
    }
}
