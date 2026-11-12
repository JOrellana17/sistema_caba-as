<?php

// Middleware que impide continuar usando la aplicación con una cuenta desactivada.

namespace App\Http\Middleware;

use App\Services\ServicioAuditoria;
use App\Services\ServicioSesiones;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerificarUsuarioActivo
{
    // Recibe ServicioAuditoria, ServicioSesiones mediante el contenedor de Laravel para reutilizarlas
    // durante la operación.
    public function __construct(private ServicioAuditoria $auditoria, private ServicioSesiones $sesiones) {}

    // Revoca una sesión cuando su cuenta quedó inactiva y devuelve JSON 401 o redirección al acceso
    // según el tipo de petición.
    public function handle(Request $solicitud, Closure $siguiente): Response
    {
        if ($solicitud->user() && ! $solicitud->user()->activo) {
            $usuario = $solicitud->user();
            $this->sesiones->cerrar($solicitud, 'revocada', 'Cuenta inactiva');
            Auth::logout();
            $solicitud->session()->invalidate();
            $solicitud->session()->regenerateToken();
            $this->auditoria->registrarAcceso('sesion_revocada', $solicitud, $usuario);

            if ($solicitud->expectsJson()) {
                return response()->json(['message' => 'Tu cuenta está inactiva.'], 401);
            }

            return redirect()->route('sesion.mostrar')->withErrors([
                'usuario' => 'Tu cuenta está inactiva. Contacta a la administración.',
            ]);
        }

        return $siguiente($solicitud);
    }
}
