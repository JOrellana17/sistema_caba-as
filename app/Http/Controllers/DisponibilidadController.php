<?php

// Controlador HTTP de Disponibilidad. Coordina solicitudes, servicios y respuestas; las rutas declaran
// los permisos de cada operación.

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarDisponibilidadRequest;
use App\Services\ServicioDisponibilidad;
use Illuminate\View\View;

class DisponibilidadController extends Controller
{
    // Recibe ServicioDisponibilidad mediante el contenedor de Laravel para reutilizarlas durante la
    // operación.
    public function __construct(private ServicioDisponibilidad $disponibilidad) {}

    // Presenta el formulario vacío o consulta cabañas disponibles cuando están completos fecha de
    // entrada, salida y cantidad de huéspedes.
    public function consultar(ConsultarDisponibilidadRequest $solicitud): View
    {
        // Usa solo los filtros aprobados por el Form Request, no todos los parámetros recibidos.
        $filtros = $solicitud->validated();
        $cabanas = null;
        if (isset($filtros['fecha_entrada'], $filtros['fecha_salida'], $filtros['cantidad_huespedes'])) {
            $cabanas = $this->disponibilidad->disponibles(
                $filtros['fecha_entrada'], $filtros['fecha_salida'], (int) $filtros['cantidad_huespedes'],
            )->select(['id', 'nombre', 'capacidad_base', 'capacidad_maxima', 'camas', 'tiene_refrigerador', 'tiene_aire_acondicionado'])
                ->withExists(['aseosPendientes as en_aseo'])
                ->orderBy('nombre')->orderBy('id')->paginate(20)->withQueryString();
        }

        return view('reservaciones.disponibilidad', compact('filtros', 'cabanas'));
    }
}
