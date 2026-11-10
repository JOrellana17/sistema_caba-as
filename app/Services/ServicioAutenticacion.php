<?php

namespace App\Services;

use App\Http\Requests\IniciarSesionRequest;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServicioAutenticacion
{
    public function __construct(private ServicioAuditoria $auditoria, private ServicioSesiones $sesiones) {}

    public function iniciar(IniciarSesionRequest $solicitud): void
    {
        $datos = $solicitud->validated();
        $campo = str_contains($datos['usuario'], '@') ? 'correo' : 'usuario';
        $cuenta = Usuario::where($campo, $datos['usuario'])->first();
        $clave = 'acceso:'.hash('sha256', ($cuenta ? 'cuenta:'.$cuenta->id : Str::lower($datos['usuario'])).'|'.$solicitud->ip());
        if (RateLimiter::tooManyAttempts($clave, 5)) {
            throw ValidationException::withMessages(['usuario' => 'Demasiados intentos. Intenta nuevamente en '.RateLimiter::availableIn($clave).' segundos.']);
        }
        $resultado = DB::transaction(function () use ($cuenta, $datos, $solicitud) {
            $usuario = $cuenta ? Usuario::lockForUpdate()->find($cuenta->id) : null;
            if ($usuario?->estaBloqueado()) {
                return 'bloqueada';
            }
            $metodo = $datos['metodo'] ?? 'contrasena';
            $hash = $usuario?->getAttribute($metodo);
            $correcta = $hash && Hash::check($datos[$metodo], $hash);
            if (! $usuario || ! $usuario->activo || ! $correcta) {
                $this->auditoria->registrarAcceso('acceso_fallido', $solicitud);
                if ($usuario) {
                    $usuario->intentos_fallidos = min(Usuario::MAXIMO_INTENTOS, $usuario->intentos_fallidos + 1);
                    if ($usuario->intentos_fallidos >= Usuario::MAXIMO_INTENTOS) {
                        $usuario->bloqueado_at = now();
                        $this->auditoria->registrarAcceso('cuenta_bloqueada', $solicitud, $usuario);
                    }
                    $usuario->save();
                }

                return $usuario?->estaBloqueado() ? 'bloqueada' : 'incorrecta';
            }
            $usuario->intentos_fallidos = 0;
            $usuario->save();

            return $usuario;
        });
        if (! $resultado instanceof Usuario) {
            RateLimiter::hit($clave, 60);
            throw ValidationException::withMessages(['usuario' => $resultado === 'bloqueada'
                ? 'Tu cuenta está bloqueada por intentos fallidos. Contacta a un administrador.'
                : 'No fue posible iniciar sesión con los datos proporcionados.']);
        }
        RateLimiter::clear($clave);
        Auth::login($resultado, $solicitud->boolean('recordar'));
        $solicitud->session()->regenerate();
        $solicitud->session()->put('password_hash_'.config('auth.defaults.guard'), Auth::guard()->hashPasswordForCookie($resultado->getAuthPassword()));
        $this->sesiones->registrar($solicitud, 'inicio');
        $this->auditoria->registrarAcceso('sesion_iniciada', $solicitud, $resultado);
    }
}
