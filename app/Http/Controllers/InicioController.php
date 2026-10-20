<?php

namespace App\Http\Controllers;

use App\Models\Reservacion;
use App\Services\ServicioDisponibilidad;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InicioController extends Controller
{
    public function ver(Request $solicitud): View
    {
        $reservaciones = null;
        if ($solicitud->user()->can('reservaciones.ver')) {
            $relaciones = ['cabana:id,nombre,deleted_at'];
            if ($solicitud->user()->canAny(['huespedes.ver', 'huespedes.buscar'])) {
                $relaciones[] = 'huesped:id,nombre,deleted_at';
            }
            $reservaciones = Reservacion::with($relaciones)
                ->whereIn('estado', Reservacion::ESTADOS_ACTIVOS)
                ->where('fecha_entrada', '>=', now(ServicioDisponibilidad::ZONA_HORARIA)->toDateString())
                ->where('salida_prevista', '>', now('UTC'))
                ->orderBy('fecha_entrada')->orderBy('entrada_prevista')->orderBy('id')
                ->paginate(20);
        }

        return view('inicio', [
            'reservaciones' => $reservaciones,
            'grupos' => $reservaciones?->getCollection()->groupBy(fn (Reservacion $registro) => $registro->fecha_entrada?->format('Y-m-d') ?? 'sin-fecha') ?? collect(),
        ]);
    }
}
