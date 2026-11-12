<?php

// Comando Artisan: Localiza una cuenta bloqueada, restablece su contador y fecha de bloqueo y registra
// el cambio realizado por consola.

namespace App\Console\Commands;

use App\Models\RegistroAuditoria;
use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DesbloquearUsuario extends Command
{
    protected $signature = 'usuarios:desbloquear {usuario}';

    protected $description = 'Retira el bloqueo por intentos fallidos de una cuenta';

    // Localiza una cuenta bloqueada, restablece su contador y fecha de bloqueo y registra el cambio
    // realizado por consola.
    public function handle(): int
    {
        $nombre = $this->argument('usuario');
        $usuario = Usuario::where('usuario', $nombre)->first();

        if (! $usuario) {
            $this->error("No existe la cuenta {$nombre}.");

            return self::FAILURE;
        }

        if (! $usuario->estaBloqueado()) {
            $this->info("La cuenta {$nombre} no está bloqueada.");

            return self::SUCCESS;
        }

        // Las escrituras de este bloque se confirman juntas; una excepción revierte sus cambios en la base de datos.
        DB::transaction(function () use ($usuario) {
            $anteriores = ['bloqueado_at' => $usuario->bloqueado_at, 'intentos_fallidos' => $usuario->intentos_fallidos];
            $usuario->bloqueado_at = null;
            $usuario->intentos_fallidos = 0;
            $usuario->save();
            RegistroAuditoria::create([
                'usuario_id' => null,
                'modulo' => 'usuarios',
                'accion' => 'cuenta_desbloqueada_por_consola',
                'registro_id' => $usuario->id,
                'valores_anteriores' => $anteriores,
                'valores_nuevos' => ['bloqueado_at' => null, 'intentos_fallidos' => 0],
            ]);
        });

        $this->info("La cuenta {$nombre} quedó desbloqueada y puede iniciar sesión.");

        return self::SUCCESS;
    }
}
