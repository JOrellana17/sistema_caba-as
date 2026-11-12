<?php

// Controlador HTTP de SesionUsuario. Coordina solicitudes, servicios y respuestas; las rutas declaran
// los permisos de cada operación.

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarSesionesRequest;
use App\Models\SesionUsuario;
use App\Services\ServicioSesiones;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SesionUsuarioController extends Controller
{
    // Solicita el historial limitado a la cuenta autenticada; un filtro enviado por el cliente no
    // amplía ese alcance.
    public function propias(ConsultarSesionesRequest $solicitud, ServicioSesiones $servicio): View
    {
        return $this->listar($solicitud, $servicio, false);
    }

    // Exige el permiso de historial global, que también requiere administrador, antes de consultar
    // sesiones de otras cuentas.
    public function todas(ConsultarSesionesRequest $solicitud, ServicioSesiones $servicio): View
    {
        Gate::forUser($solicitud->user()->fresh())->authorize('sesiones.ver_todas');

        return $this->listar($solicitud, $servicio, true);
    }

    // Actualiza vencimientos calculados, aplica filtros validados y pagina el historial; restringe el
    // usuario cuando se trata de la vista personal.
    private function listar(ConsultarSesionesRequest $solicitud, ServicioSesiones $servicio, bool $todas): View
    {
        $servicio->vencer();
        $datos = $solicitud->validated();
        $consulta = SesionUsuario::with('usuario')->when(! $todas, fn ($q) => $q->where('usuario_id', $solicitud->user()->id));
        if ($todas && ! empty($datos['usuario'])) {
            $consulta->whereHas('usuario', fn ($q) => $q->where('usuario', $datos['usuario']));
        }
        foreach (['estado', 'desde', 'hasta'] as $campo) {
            if (! empty($datos[$campo])) {
                match ($campo) {
                    'estado' => $consulta->where('estado', $datos[$campo]),
                    'desde' => $consulta->whereDate('iniciada_en', '>=', $datos[$campo]),
                    'hasta' => $consulta->whereDate('iniciada_en', '<=', $datos[$campo]),
                };
            }
        }

        return view('sesiones.listar', ['sesiones' => $consulta->orderByDesc('iniciada_en')->orderByDesc('id')->paginate(20)->withQueryString(), 'todas' => $todas]);
    }
}
