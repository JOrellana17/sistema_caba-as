<?php

namespace App\Services;

use App\Models\RegistroAuditoria;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ServicioAuditoria
{
    public function registrarCambio(Request $solicitud, string $modulo, string $accion, int $registroId, array $anteriores = [], array $nuevos = []): void
    {
        $campos = ['nombre', 'usuario', 'activo', 'codigo', 'descripcion', 'roles', 'permisos', 'deleted_at'];
        if ($modulo === 'cabanas') {
            $campos = ['nombre', 'slug', 'capacidad_base', 'capacidad_maxima', 'precio_base',
                'precio_persona_adicional', 'precio_base_usd', 'precio_persona_adicional_usd', 'estado', 'activa', 'imagen_principal', 'creado_por', 'deleted_at'];
        }
        if ($modulo === 'reservaciones') {
            $campos = ['codigo', 'cabana_id', 'huesped_id', 'fecha_entrada', 'fecha_salida', 'entrada_prevista', 'salida_prevista',
                'cantidad_huespedes', 'estado', 'capacidad_base_aplicada', 'precio_base', 'precio_persona_adicional',
                'noches', 'personas_adicionales', 'subtotal', 'moneda', 'creado_por', 'cancelado_por',
                'fecha_confirmacion', 'fecha_entrada_registrada', 'fecha_salida_registrada', 'fecha_cancelacion', 'fecha_no_presentado', 'deleted_at'];
        }
        if ($modulo === 'huespedes') {
            $campos = ['activo', 'vip', 'frecuente', 'lista_negra', 'deleted_at', 'campos_modificados'];
        }
        if ($modulo === 'calendario') {
            $campos = ['cabana_id', 'fuente_id', 'reservacion_id', 'origen', 'inicio', 'fin', 'bloquea', 'estado', 'activa', 'activo', 'conflicto', 'discrepancia', 'procesados', 'retirados'];
        }
        RegistroAuditoria::create([
            'usuario_id' => $solicitud->user()?->id,
            'modulo' => $modulo,
            'accion' => $accion,
            'registro_id' => $registroId,
            'valores_anteriores' => Arr::only($anteriores, $campos),
            'valores_nuevos' => Arr::only($nuevos, $campos),
            'direccion_ip' => $solicitud->ip(),
            'agente_usuario' => Str::substr($solicitud->userAgent() ?? '', 0, 500),
        ]);
    }

    public function registrarAcceso(string $accion, Request $solicitud, ?Usuario $usuario = null): void
    {
        RegistroAuditoria::create([
            'usuario_id' => $usuario?->id,
            'modulo' => 'autenticacion',
            'accion' => $accion,
            'direccion_ip' => $solicitud->ip(),
            'agente_usuario' => Str::substr($solicitud->userAgent() ?? '', 0, 500),
        ]);
    }
}
