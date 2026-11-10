<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $tabla) {
            $tabla->string('pin')->nullable();
            $tabla->unsignedInteger('version_credenciales')->default(0);
        });
        Schema::create('sesiones_usuarios', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $tabla->string('referencia', 64)->unique();
            $tabla->timestamp('iniciada_en');
            $tabla->timestamp('actividad_en');
            $tabla->timestamp('vence_en');
            $tabla->timestamp('finalizada_en')->nullable();
            $tabla->string('estado', 20)->default('activa');
            $tabla->string('motivo', 100)->nullable();
            $tabla->string('direccion_ip', 45)->nullable();
            $tabla->string('agente_usuario', 500)->nullable();
            $tabla->string('origen', 30);
            $tabla->timestamps();
            $tabla->index(['usuario_id', 'iniciada_en']);
            $tabla->index(['estado', 'vence_en']);
        });
        DB::transaction(function () {
            foreach (['usuarios.restablecer_pin', 'sesiones.ver_todas'] as $codigo) {
                DB::table('permisos')->insertOrIgnore(['codigo' => $codigo, 'protegido' => true, 'created_at' => now(), 'updated_at' => now()]);
                $rol = DB::table('roles')->where('codigo', 'administrador')->value('id');
                if ($rol) {
                    DB::table('permiso_rol')->insertOrIgnore(['rol_id' => $rol, 'permiso_id' => DB::table('permisos')->where('codigo', $codigo)->value('id')]);
                }
            }
        });
    }

    public function down(): void
    {
        // Revertir elimina el historial y los PIN: preferir una migración correctiva con datos reales.
        Schema::dropIfExists('sesiones_usuarios');
        Schema::table('usuarios', fn (Blueprint $tabla) => $tabla->dropColumn(['pin', 'version_credenciales']));
    }
};
