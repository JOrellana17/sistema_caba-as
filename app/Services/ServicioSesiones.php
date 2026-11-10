<?php

namespace App\Services;

use App\Models\SesionUsuario;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicioSesiones
{
    public function vencer(): void
    {
        SesionUsuario::where('estado', 'activa')->where('vence_en', '<=', now())
            ->update(['estado' => 'vencida', 'finalizada_en' => DB::raw('vence_en'), 'motivo' => 'Inactividad (vencimiento calculado)']);
    }

    public function registrar(Request $solicitud, string $origen = 'observada'): void
    {
        $usuario = $solicitud->user();
        if (! $usuario || ! $usuario->activo) {
            return;
        }
        $this->vencer();
        $referencia = hash('sha256', $solicitud->session()->getId());
        $registro = SesionUsuario::firstOrCreate(['referencia' => $referencia], [
            'usuario_id' => $usuario->id, 'iniciada_en' => now(), 'actividad_en' => now(),
            'vence_en' => now()->addMinutes(config('session.lifetime')), 'estado' => 'activa',
            'direccion_ip' => $solicitud->ip(), 'agente_usuario' => Str::substr($solicitud->userAgent() ?? '', 0, 500),
            'origen' => $origen,
        ]);
        if ($registro->estado === 'activa') {
            $registro->update(['actividad_en' => now(), 'vence_en' => now()->addMinutes(config('session.lifetime'))]);
        }
        $solicitud->session()->put('version_credenciales', $usuario->version_credenciales);
    }

    public function cerrar(Request $solicitud, string $estado = 'cerrada', string $motivo = 'Cierre de sesión'): void
    {
        $this->vencer();
        SesionUsuario::where('referencia', hash('sha256', $solicitud->session()->getId()))->where('estado', 'activa')
            ->update(['estado' => $estado, 'motivo' => $motivo, 'finalizada_en' => now()]);
    }

    public function revocar(Usuario $usuario, string $motivo): void
    {
        $this->vencer();
        SesionUsuario::where('usuario_id', $usuario->id)->where('estado', 'activa')
            ->update(['estado' => 'revocada', 'motivo' => $motivo, 'finalizada_en' => now()]);
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table'))->where('user_id', $usuario->id)->delete();
        }
    }
}
