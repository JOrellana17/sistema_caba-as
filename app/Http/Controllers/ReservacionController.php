<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarReservacionRequest;
use App\Http\Requests\ConsultarReservacionesRequest;
use App\Http\Requests\GuardarReservacionRequest;
use App\Models\Cabana;
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
    public function __construct(private ServicioReservaciones $reservaciones) {}

    public function listar(ConsultarReservacionesRequest $solicitud): View
    {
        $filtros = $solicitud->validated();
        $consulta = Reservacion::with(['cabana:id,nombre,deleted_at']);
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

    public function crear(Request $solicitud): View
    {
        return $this->formulario($solicitud, new Reservacion);
    }

    public function editar(Request $solicitud, Reservacion $reservacion): View
    {
        return $this->formulario($solicitud, $reservacion);
    }

    public function ver(Reservacion $reservacion): View
    {
        $reservacion->load(['cabana:id,nombre,deleted_at']);
        if (Gate::any(['huespedes.ver', 'huespedes.buscar'])) {
            $reservacion->load('huesped');
        }

        return view('reservaciones.ver', compact('reservacion'));
    }

    public function guardar(GuardarReservacionRequest $solicitud): RedirectResponse
    {
        $registro = $this->reservaciones->guardar($solicitud, $solicitud->validated());

        return $this->volver($solicitud, $registro);
    }

    public function actualizar(GuardarReservacionRequest $solicitud, Reservacion $reservacion): RedirectResponse
    {
        $registro = $this->reservaciones->guardar($solicitud, $solicitud->validated(), $reservacion);

        return $this->volver($solicitud, $registro);
    }

    public function pendiente(CambiarReservacionRequest $solicitud, Reservacion $reservacion): RedirectResponse
    {
        return $this->transicion($solicitud, $reservacion, 'pendiente');
    }

    public function confirmar(CambiarReservacionRequest $solicitud, Reservacion $reservacion): RedirectResponse
    {
        return $this->transicion($solicitud, $reservacion, 'confirmada');
    }

    public function entrada(CambiarReservacionRequest $solicitud, Reservacion $reservacion): RedirectResponse
    {
        return $this->transicion($solicitud, $reservacion, 'hospedada');
    }

    public function salida(CambiarReservacionRequest $solicitud, Reservacion $reservacion): RedirectResponse
    {
        return $this->transicion($solicitud, $reservacion, 'finalizada');
    }

    public function cancelar(CambiarReservacionRequest $solicitud, Reservacion $reservacion): RedirectResponse
    {
        return $this->transicion($solicitud, $reservacion, 'cancelada');
    }

    public function noPresentado(CambiarReservacionRequest $solicitud, Reservacion $reservacion): RedirectResponse
    {
        return $this->transicion($solicitud, $reservacion, 'no_presentado');
    }

    public function horarios(CambiarReservacionRequest $solicitud, Reservacion $reservacion): RedirectResponse
    {
        $this->reservaciones->horarios($solicitud, $reservacion, $solicitud->validated());

        return $this->volver($solicitud, $reservacion);
    }

    public function eliminar(Request $solicitud, Reservacion $reservacion): RedirectResponse
    {
        $this->reservaciones->eliminar($solicitud, $reservacion);

        return redirect()->route($solicitud->user()->can('reservaciones.ver') ? 'reservaciones.listar' : 'inicio')->with('estado', 'Reservación eliminada lógicamente.');
    }

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

    private function transicion(CambiarReservacionRequest $solicitud, Reservacion $reservacion, string $destino): RedirectResponse
    {
        $this->reservaciones->cambiarEstado($solicitud, $reservacion, $destino, $solicitud->validated());

        return $this->volver($solicitud, $reservacion);
    }

    private function volver(Request $solicitud, Reservacion $reservacion): RedirectResponse
    {
        return redirect()->to($solicitud->user()->can('reservaciones.ver') ? route('reservaciones.ver', $reservacion) : route('inicio'))
            ->with('estado', 'Operación realizada correctamente.');
    }
}
