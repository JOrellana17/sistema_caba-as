<?php

// Servicio de generador credenciales. Centraliza las operaciones descritas en sus métodos para que los
// controladores reutilicen las mismas reglas.

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Str;

class ServicioGeneradorCredenciales
{
    // Devuelve un PIN aleatorio, una contraseña aleatoria o un usuario sugerido desde el nombre.
    // Comprueba usuarios eliminados al evitar duplicados; no crea ni modifica cuentas.
    public function generar(array $datos): string
    {
        if ($datos['tipo'] === 'pin') {
            return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        }
        if ($datos['tipo'] === 'contrasena') {
            return Str::password(16);
        }
        $base = substr(Str::slug($datos['nombre'], '.'), 0, 80) ?: 'usuario';
        $usuario = $base;
        for ($numero = 1; Usuario::withTrashed()->where('usuario', $usuario)->exists(); $numero++) {
            $usuario = $base.'.'.$numero;
        }

        return $usuario;
    }
}
