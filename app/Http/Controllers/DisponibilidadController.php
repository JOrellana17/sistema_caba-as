<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarDisponibilidadRequest;
use App\Services\ServicioDisponibilidad;
use Illuminate\View\View;

class DisponibilidadController extends Controller
{
    public function __construct(private ServicioDisponibilidad $disponibilidad) {}

    public function consultar(ConsultarDisponibilidadRequest $solicitud): View
    {
        $filtros = $solicitud->validated();
        $cabanas = null;
        if (isset($filtros['fecha_entrada'], $filtros['fecha_salida'], $filtros['cantidad_huespedes'])) {
            $cabanas = $this->disponibilidad->disponibles(
                $filtros['fecha_entrada'], $filtros['fecha_salida'], (int) $filtros['cantidad_huespedes'],
            )->select(['id', 'nombre', 'capacidad_base', 'capacidad_maxima'])
                ->orderBy('nombre')->orderBy('id')->paginate(20)->withQueryString();
        }

        return view('reservaciones.disponibilidad', compact('filtros', 'cabanas'));
    }
}
