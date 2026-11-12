<?php

// Servicio de autenticacion. Centraliza las operaciones descritas en sus métodos para que los
// controladores reutilicen las mismas reglas.

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
    // Recibe ServicioAuditoria, ServicioSesiones mediante el contenedor de Laravel para reutilizarlas
    // durante la operación.
    public function __construct(private ServicioAuditoria $auditoria, private ServicioSesiones $sesiones) {}

    // Busca la cuenta por usuario o correo y verifica la contraseña o el PIN seleccionado. Serializa
    // el contador de fallos, bloquea al tercero y lo reinicia tras un acceso correcto; después renueva
    // la sesión y registra el acceso.
    public function iniciar(IniciarSesionRequest $solicitud): void
    {
        $datos = $solicitud->validated();
        $campo = str_contains($datos['usuario'], '@') ? 'correo' : 'usuario';
        $cuenta = Usuario::where($campo, $datos['usuario'])->first();
        // Usuario y correo de la misma cuenta comparten el límite por IP; el contador persistente pertenece a la cuenta.
        $clave = 'acceso:'.hash('sha256', ($cuenta ? 'cuenta:'.$cuenta->id : Str::lower($datos['usuario'])).'|'.$solicitud->ip());
        if (RateLimiter::tooManyAttempts($clave, 5)) {
            throw ValidationException::withMessages(['usuario' => 'Demasiados intentos. Intenta nuevamente en '.RateLimiter::availableIn($clave).' segundos.']);
        }
        // Las escrituras de este bloque se confirman juntas; una excepción revierte sus cambios en la base de datos.
        $resultado = DB::transaction(function () use ($cuenta, $datos, $solicitud) {
            // El bloqueo de fila evita que dos intentos simultáneos pierdan incrementos del contador.
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
            // Una credencial válida antes del tercer fallo reinicia la secuencia; una cuenta ya bloqueada salió antes.
            $usuario->intentos_fallidos = 0;
            $usuario->save();

            return $usuario;
        });
        // Se rechaza después de confirmar la transacción para conservar el fallo y su auditoría.
        if (! $resultado instanceof Usuario) {
            RateLimiter::hit($clave, 60);
            throw ValidationException::withMessages(['usuario' => $resultado === 'bloqueada'
                ? 'Tu cuenta está bloqueada por intentos fallidos. Contacta a un administrador.'
                : 'No fue posible iniciar sesión con los datos proporcionados.']);
        }
        RateLimiter::clear($clave);
        Auth::login($resultado, $solicitud->boolean('recordar'));
        // Renueva el identificador tras autenticar para impedir fijación de sesión.
        $solicitud->session()->regenerate();
        $solicitud->session()->put('password_hash_'.config('auth.defaults.guard'), Auth::guard()->hashPasswordForCookie($resultado->getAuthPassword()));
        $this->sesiones->registrar($solicitud, 'inicio');
        $this->auditoria->registrarAcceso('sesion_iniciada', $solicitud, $resultado);
    }
}
