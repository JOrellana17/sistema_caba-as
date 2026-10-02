<?php

namespace App\Http\Middleware;

use App\Services\ServicioAuditoria;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerificarUsuarioActivo
{
    public function __construct(private ServicioAuditoria $auditoria) {}

    public function handle(Request $solicitud, Closure $siguiente): Response
    {
        if ($solicitud->user() && ! $solicitud->user()->activo) {
            $usuario = $solicitud->user();
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
