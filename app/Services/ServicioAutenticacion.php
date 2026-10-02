<?php

namespace App\Services;

use App\Http\Requests\IniciarSesionRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServicioAutenticacion
{
    public function __construct(private ServicioAuditoria $auditoria) {}

    public function iniciar(IniciarSesionRequest $solicitud): void
    {
        $datos = $solicitud->validated();
        $clave = 'acceso:'.hash('sha256', Str::lower($datos['usuario']).'|'.$solicitud->ip());

        if (RateLimiter::tooManyAttempts($clave, 5)) {
            $segundos = RateLimiter::availableIn($clave);

            throw ValidationException::withMessages([
                'usuario' => "Demasiados intentos. Intenta nuevamente en {$segundos} segundos.",
            ]);
        }

        if (! Auth::attempt([
            'usuario' => $datos['usuario'],
            'password' => $datos['contrasena'],
            'activo' => true,
        ], $solicitud->boolean('recordar'))) {
            RateLimiter::hit($clave, 60);
            $this->auditoria->registrarAcceso('acceso_fallido', $solicitud);

            throw ValidationException::withMessages([
                'usuario' => 'No fue posible iniciar sesión con los datos proporcionados.',
            ]);
        }

        RateLimiter::clear($clave);
        $solicitud->session()->regenerate();
        $solicitud->session()->put(
            'password_hash_'.config('auth.defaults.guard'),
            Auth::guard()->hashPasswordForCookie(Auth::user()->getAuthPassword()),
        );
        $this->auditoria->registrarAcceso('sesion_iniciada', $solicitud, Auth::user());
    }
}
