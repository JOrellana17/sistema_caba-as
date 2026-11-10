<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarSesionesRequest;
use App\Models\SesionUsuario;
use App\Services\ServicioSesiones;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SesionUsuarioController extends Controller
{
    public function propias(ConsultarSesionesRequest $solicitud, ServicioSesiones $servicio): View
    {
        return $this->listar($solicitud, $servicio, false);
    }

    public function todas(ConsultarSesionesRequest $solicitud, ServicioSesiones $servicio): View
    {
        Gate::forUser($solicitud->user()->fresh())->authorize('sesiones.ver_todas');

        return $this->listar($solicitud, $servicio, true);
    }

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
