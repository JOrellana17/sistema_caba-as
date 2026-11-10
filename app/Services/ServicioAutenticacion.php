<?php

namespace App\Services;

use App\Http\Requests\IniciarSesionRequest;
use App\Models\Usuario;
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

        $cuenta = Usuario::where('usuario', $datos['usuario'])->first();

        if ($cuenta && $cuenta->estaBloqueado()) {
            // No se verifica la contraseña ni se cuenta el intento.
            throw ValidationException::withMessages([
                'usuario' => 'Tu cuenta está bloqueada por intentos fallidos. Contacta a un administrador.',
            ]);
        }

        if (! Auth::attempt([
            'usuario' => $datos['usuario'],
            'password' => $datos['contrasena'],
            'activo' => true,
        ], $solicitud->boolean('recordar'))) {
            RateLimiter::hit($clave, 60);
            $this->auditoria->registrarAcceso('acceso_fallido', $solicitud);

            if ($cuenta && $this->registrarIntentoFallido($cuenta)) {
                $this->auditoria->registrarAcceso('cuenta_bloqueada', $solicitud, $cuenta);

                throw ValidationException::withMessages([
                    'usuario' => 'Tu cuenta está bloqueada por intentos fallidos. Contacta a un administrador.',
                ]);
            }

            throw ValidationException::withMessages([
                'usuario' => 'No fue posible iniciar sesión con los datos proporcionados.',
            ]);
        }

        RateLimiter::clear($clave);
        if ($cuenta) {
            Usuario::whereKey($cuenta->id)->update(['intentos_fallidos' => 0]);
        }
        $solicitud->session()->regenerate();
        $solicitud->session()->put(
            'password_hash_'.config('auth.defaults.guard'),
            Auth::guard()->hashPasswordForCookie(Auth::user()->getAuthPassword()),
        );
        $this->auditoria->registrarAcceso('sesion_iniciada', $solicitud, Auth::user());
    }

    /**
     * Cuenta el intento fallido de forma atómica y bloquea la cuenta al
     * alcanzar el máximo permitido.
     */
    private function registrarIntentoFallido(Usuario $cuenta): bool
    {
        Usuario::whereKey($cuenta->id)->whereNull('bloqueado_at')->increment('intentos_fallidos');
        $cuenta->refresh();

        if ($cuenta->intentos_fallidos < Usuario::MAXIMO_INTENTOS) {
            return false;
        }

        Usuario::whereKey($cuenta->id)->whereNull('bloqueado_at')->update(['bloqueado_at' => now()]);

        return true;
    }
}
