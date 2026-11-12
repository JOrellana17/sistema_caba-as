<?php

// Controlador HTTP de Reservacion. Coordina solicitudes, servicios y respuestas; las rutas declaran
// los permisos de cada operación.

namespace App\Http\Controllers;

use App\Http\Requests\CambiarReservacionRequest;
use App\Http\Requests\ConsultarReservacionesRequest;
use App\Http\Requests\GuardarReservacionRequest;
use App\Models\Cabana;
use App\Models\EventoCalendario;
use App\Models\Huesped;
use App\Models\Reservacion;
use App\Services\ServicioDisponibilidad;
use App\Services\ServicioReservaciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReservacionController extends Controller
{
    // Recibe ServicioReservaciones mediante el contenedor de Laravel para reutilizarlas durante la
    // operación.
    public function __construct(private ServicioReservaciones $reservaciones) {}

    // Consulta y pagina los registros de Reservacion para su listado; conserva el orden y los filtros
    // definidos en esta acción.
    public function listar(ConsultarReservacionesRequest $solicitud): View
    {
        // Usa solo los filtros aprobados por el Form Request, no todos los parámetros recibidos.
        $filtros = $solicitud->validated();
        $consulta = Reservacion::with(['cabana:id,nombre,deleted_at', 'aseo:id,reservacion_id,confirmado_en']);
        foreach (['codigo', 'estado', 'cabana_id', 'huesped_id'] as $campo) {
            if ($solicitud->filled($campo)) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }
        if ($solicitud->filled('desde')) {
            $consulta->where('fecha_entrada', '>=', $filtros['desde']);
        }
        if ($solicitud->filled('hasta')) {
            $consulta->where('fecha_entrada', '<=', $filtros['hasta']);
        }

        return view('reservaciones.listar', [
            'reservaciones' => $consulta->orderByDesc('fecha_entrada')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filtros' => $filtros,
        ]);
    }

    // Prepara una reservación nueva; si procede de un evento externo autorizado, copia cabaña y
    // período como propuesta y exige completar los demás datos.
    public function crear(Request $solicitud): View
    {
        $reservacion = new Reservacion;
        if ($solicitud->filled('evento_id')) {
            Gate::authorize('calendario.vincular');
            // Valida los datos de esta acción antes de consultar o modificar registros relacionados.
            $solicitud->validate(['evento_id' => ['required', 'integer', 'exists:eventos_calendario,id']]);
            $evento = EventoCalendario::findOrFail($solicitud->integer('evento_id'));
            abort_unless($evento->bloquea && ! $evento->reservacion_id && $evento->origen !== 'cec', 422);
            $reservacion->forceFill([
                'cabana_id' => $evento->cabana_id,
                'fecha_entrada' => $evento->inicio->setTimezone(ServicioDisponibilidad::ZONA_HORARIA)->format('Y-m-d'),
                'fecha_salida' => $evento->fin->setTimezone(ServicioDisponibilidad::ZONA_HORARIA)->format('Y-m-d'),
                'entrada_prevista' => $evento->inicio,
                'salida_prevista' => $evento->fin,
            ]);
        }

        return $this->formulario($solicitud, $reservacion);
    }

    // Presenta el formulario de Reservacion con el registro resuelto por Laravel para su edición.
    public function editar(Request $solicitud, Reservacion $reservacion): View
    {
        return $this->formulario($solicitud, $reservacion);
    }

    // Carga el detalle de Reservacion y sus relaciones necesarias para presentar la ficha.
    public function ver(Reservacion $reservacion): View
    {
        $reservacion->load(['cabana:id,nombre,deleted_at', 'cabana.aseosPendientes', 'aseo', 'eventoCalendario']);
        if (Gate::any(['huespedes.ver', 'huespedes.buscar'])) {
            $reservacion->load('huesped');
        }

        return view('reservaciones.ver', compact('reservacion'));
    }

    // Entrega los datos validados de Reservacion al servicio responsable de guardarlos y devuelve la
    // respuesta de confirmación.
    public function guardar(GuardarReservacionRequest $solicitud): RedirectResponse
    {
        $registro = $this->reservaciones->guardar($solicitud, $solicitud->validated());

        return $this->volver($solicitud, $registro);
    }

    // Envía el registro de Reservacion y los datos validados al servicio para aplicar una
    // actualización autorizada.
    public function actualizar(GuardarReservacionRequest $solicitud, Reservacion $reservacion): RedirectResponse
    {
        $registro = $this->reservaciones->guardar($solicitud, $solicitud->validated(), $reservacion);

        return $this->volver($solicitud, $registro);
    }

    // Solicita la transición a confirmada; el servicio comprueba permisos, estado y disponibilidad.
    public function confirmar(CambiarReservacionRequest $solicitud, Reservacion $reservacion): RedirectResponse
    {
        return $this->transicion($solicitud, $reservacion, 'confirmada');
    }

    // Solicita registrar la entrada y pasar a hospedada; las restricciones de horario, ocupación y
    // aseo se comprueban en el servicio.
    public function entrada(CambiarReservacionRequest $solicitud, Reservacion $reservacion): RedirectResponse
    {
        return $this->transicion($solicitud, $reservacion, 'hospedada');
    }

    // Solicita finalizar la estancia; el servicio registra la salida y abre el ciclo de aseo
    // correspondiente.
    public function salida(CambiarReservacionRequest $solicitud, Reservacion $reservacion): RedirectResponse
    {
        return $this->transicion($solicitud, $reservacion, 'finalizada');
    }

    // Solicita cancelar una reservación admitida por el flujo y conserva su historial.
    public function cancelar(CambiarReservacionRequest $solicitud, Reservacion $reservacion): RedirectResponse
    {
        return $this->transicion($solicitud, $reservacion, 'cancelada');
    }

    // Solicita marcar no presentado; el servicio exige que se haya cumplido la salida prevista.
    public function noPresentado(CambiarReservacionRequest $solicitud, Reservacion $reservacion): RedirectResponse
    {
        return $this->transicion($solicitud, $reservacion, 'no_presentado');
    }

    // Envía las horas validadas al servicio para comprobar permisos especiales y disponibilidad.
    public function horarios(CambiarReservacionRequest $solicitud, Reservacion $reservacion): RedirectResponse
    {
        $this->reservaciones->horarios($solicitud, $reservacion, $solicitud->validated());

        return $this->volver($solicitud, $reservacion);
    }

    // Delega la eliminación de Reservacion al servicio, donde se verifican permisos y restricciones
    // antes de modificar los datos.
    public function eliminar(Request $solicitud, Reservacion $reservacion): RedirectResponse
    {
        $this->reservaciones->eliminar($solicitud, $reservacion);

        return redirect()->route($solicitud->user()->can('reservaciones.ver') ? 'reservaciones.listar' : 'inicio')->with('estado', 'Reservación eliminada lógicamente.');
    }

    // Prepara cabañas, huésped seleccionado y horas locales. Recupera la selección anterior tras
    // errores sin crear otra ficha.
    private function formulario(Request $solicitud, Reservacion $reservacion): View
    {
        Gate::authorize('huespedes.buscar');
        $seleccionId = $solicitud->old('huesped_id', $reservacion->huesped_id);
        $seleccion = filter_var($seleccionId, FILTER_VALIDATE_INT) !== false ? Huesped::find((int) $seleccionId) : null;

        return view('reservaciones.formulario', [
            'reservacion' => $reservacion, 'seleccion' => $seleccion,
            'cabanas' => Cabana::orderBy('nombre')->orderBy('id')->get(['id', 'nombre', 'capacidad_maxima']),
            'horaEntrada' => $reservacion->entrada_prevista?->setTimezone(ServicioDisponibilidad::ZONA_HORARIA)->format('H:i') ?? '14:00',
            'horaSalida' => $reservacion->salida_prevista?->setTimezone(ServicioDisponibilidad::ZONA_HORARIA)->format('H:i') ?? '11:00',
        ]);
    }

    // Delega todas las transiciones al mismo servicio para que cada botón aplique las mismas reglas de
    // estados.
    private function transicion(CambiarReservacionRequest $solicitud, Reservacion $reservacion, string $destino): RedirectResponse
    {
        $this->reservaciones->cambiarEstado($solicitud, $reservacion, $destino, $solicitud->validated());

        return $this->volver($solicitud, $reservacion);
    }

    // Selecciona un destino que el usuario pueda consultar y agrega un mensaje de confirmación a la
    // siguiente respuesta.
    private function volver(Request $solicitud, Reservacion $reservacion): RedirectResponse
    {
        return redirect()->to($solicitud->user()->can('reservaciones.ver') ? route('reservaciones.ver', $reservacion) : route('inicio'))
            ->with('estado', 'Operación realizada correctamente.');
    }
}
