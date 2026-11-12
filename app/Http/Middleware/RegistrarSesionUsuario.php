<?php

// Middleware de versión de credenciales, actividad de sesión y respuestas privadas.

namespace App\Http\Middleware;

use App\Services\ServicioSesiones;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RegistrarSesionUsuario
{
    // Recibe ServicioSesiones mediante el contenedor de Laravel para reutilizarlas durante la
    // operación.
    public function __construct(private ServicioSesiones $sesiones) {}

    // Compara la versión de credenciales para rechazar sesiones antiguas; registra la actividad de las
    // admitidas y evita que la respuesta autenticada quede en caché.
    public function handle(Request $solicitud, Closure $siguiente): Response
    {
        $usuario = $solicitud->user();
        if ($usuario && $solicitud->session()->has('version_credenciales')
            && (int) $solicitud->session()->get('version_credenciales') !== $usuario->version_credenciales) {
            $this->sesiones->cerrar($solicitud, 'revocada', 'Credenciales modificadas');
            Auth::logoutCurrentDevice();
            $solicitud->session()->invalidate();
            $solicitud->session()->regenerateToken();

            return $solicitud->expectsJson() ? response()->json(['message' => 'Vuelve a iniciar sesión.'], 401)
                : redirect()->route('sesion.mostrar')->withErrors(['usuario' => 'Tus credenciales cambiaron. Vuelve a iniciar sesión.']);
        }
        $this->sesiones->registrar($solicitud, Auth::viaRemember() ? 'recordarme' : 'observada');
        $respuesta = $siguiente($solicitud);
        $respuesta->headers->set('Cache-Control', 'no-store, private');

        return $respuesta;
    }
}
