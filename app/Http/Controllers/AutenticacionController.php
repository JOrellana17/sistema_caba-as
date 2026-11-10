<?php

namespace App\Http\Controllers;

use App\Http\Requests\IniciarSesionRequest;
use App\Services\ServicioAuditoria;
use App\Services\ServicioAutenticacion;
use App\Services\ServicioSesiones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AutenticacionController extends Controller
{
    public function mostrar(): View
    {
        return view('autenticacion.iniciar-sesion');
    }

    public function iniciar(IniciarSesionRequest $solicitud, ServicioAutenticacion $autenticacion): RedirectResponse
    {
        $autenticacion->iniciar($solicitud);

        if ($solicitud->user()->esAseador()) {
            $solicitud->session()->forget('url.intended');

            return redirect()->route('aseo.listar');
        }

        return redirect()->intended(route('inicio'));
    }

    public function cerrar(Request $solicitud, ServicioAuditoria $auditoria, ServicioSesiones $sesiones): RedirectResponse
    {
        $usuario = $solicitud->user();
        $sesiones->cerrar($solicitud);
        Auth::logout();
        $solicitud->session()->invalidate();
        $solicitud->session()->regenerateToken();
        $auditoria->registrarAcceso('sesion_cerrada', $solicitud, $usuario);

        return redirect()->route('sesion.mostrar')->with('estado', 'Cerraste sesión correctamente.');
    }
}
