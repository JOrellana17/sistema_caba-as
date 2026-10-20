<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('reservaciones')->where('estado', 'por_confirmar')->select('id')->chunkById(100, function ($registros) {
            DB::transaction(function () use ($registros) {
                foreach ($registros as $candidata) {
                    $registro = DB::table('reservaciones')->where('id', $candidata->id)->lockForUpdate()->first();
                    if (! $registro || $registro->estado !== 'por_confirmar') {
                        continue;
                    }
                    $ahora = now('UTC');
                    DB::table('reservaciones')->where('id', $registro->id)->update(['estado' => 'pendiente', 'updated_at' => $ahora]);
                    DB::table('registros_auditoria')->insert([
                        'usuario_id' => null, 'modulo' => 'reservaciones', 'accion' => 'estado_unificado', 'registro_id' => $registro->id,
                        'valores_anteriores' => json_encode(['estado' => 'por_confirmar']),
                        'valores_nuevos' => json_encode(['estado' => 'pendiente']),
                        'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                }
            }, 3);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La unificación de estados conserva el historial y no se revierte automáticamente. Usa una migración correctiva.');
    }
};
