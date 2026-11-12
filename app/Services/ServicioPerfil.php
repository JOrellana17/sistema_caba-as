<?php

// Servicio de perfil. Centraliza las operaciones descritas en sus métodos para que los controladores
// reutilicen las mismas reglas.

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServicioPerfil
{
    // Recibe ServicioSesiones, ServicioAuditoria mediante el contenedor de Laravel para reutilizarlas
    // durante la operación.
    public function __construct(private ServicioSesiones $sesiones, private ServicioAuditoria $auditoria) {}

    // Verifica la contraseña actual dentro de una transacción, cambia la credencial solicitada y
    // revoca sesiones previas. Renueva la sesión del titular y registra el cambio sin secretos.
    public function cambiar(Request $solicitud, array $datos): void
    {
        // Las escrituras de este bloque se confirman juntas; una excepción revierte sus cambios en la base de datos.
        $usuario = DB::transaction(function () use ($solicitud, $datos) {
            $usuario = Usuario::lockForUpdate()->findOrFail($solicitud->user()->id);
            abort_unless($usuario->activo, 403);
            if (! Hash::check($datos['contrasena_actual'], $usuario->contrasena)) {
                throw ValidationException::withMessages(['contrasena_actual' => 'La contraseña actual no es correcta.']);
            }
            if ($datos['operacion'] === 'contrasena') {
                $usuario->contrasena = $datos['contrasena'];
            } else {
                $usuario->pin = $datos['operacion'] === 'retirar_pin' ? null : $datos['pin'];
            }
            $usuario->remember_token = Str::random(60);
            $usuario->version_credenciales++;
            $usuario->save();
            $this->sesiones->revocar($usuario, 'Credenciales modificadas por su titular');
            $this->auditoria->registrarCambio($solicitud, 'usuarios', 'perfil_'.$datos['operacion'], $usuario->id);

            return $usuario;
        });
        Auth::setUser($usuario);
        $solicitud->session()->regenerate(true);
        $solicitud->session()->put('password_hash_'.config('auth.defaults.guard'), Auth::guard()->hashPasswordForCookie($usuario->getAuthPassword()));
        $this->sesiones->registrar($solicitud, 'renovada');
    }
}
