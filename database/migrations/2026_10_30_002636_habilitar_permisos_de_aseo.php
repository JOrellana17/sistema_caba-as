<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $ahora = now();
            foreach (['aseo.ver', 'aseo.confirmar'] as $codigo) {
                DB::table('permisos')->insertOrIgnore(['codigo' => $codigo, 'protegido' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
                DB::table('permisos')->where('codigo', $codigo)->update(['protegido' => true]);
            }
            $aseador = DB::table('roles')->where('codigo', 'aseador')->first();
            $permisos = DB::table('permisos')->whereIn('codigo', ['aseo.ver', 'aseo.confirmar'])->pluck('id')->all();
            if ($aseador && (DB::table('permiso_rol')->where('rol_id', $aseador->id)->whereNotIn('permiso_id', $permisos)->exists()
                || DB::table('rol_usuario')->where('rol_id', $aseador->id)->exists())) {
                throw new LogicException('Ya existe un rol Aseador en uso. Revisa su acceso antes de habilitar esta ampliación.');
            }
            DB::table('roles')->insertOrIgnore([
                'codigo' => 'aseador', 'nombre' => 'Aseador', 'descripcion' => 'Consulta y confirmación de aseo de cabañas.',
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
            foreach (DB::table('roles')->whereIn('codigo', ['administrador', 'empleado', 'aseador'])->get() as $rol) {
                $anteriores = DB::table('permiso_rol')->where('rol_id', $rol->id)->orderBy('permiso_id')->pluck('permiso_id')->all();
                foreach ($permisos as $permisoId) {
                    DB::table('permiso_rol')->insertOrIgnore(['rol_id' => $rol->id, 'permiso_id' => $permisoId]);
                }
                $nuevos = DB::table('permiso_rol')->where('rol_id', $rol->id)->orderBy('permiso_id')->pluck('permiso_id')->all();
                if ($anteriores !== $nuevos && ($rol->codigo !== 'aseador' || $aseador)) {
                    DB::table('registros_auditoria')->insert([
                        'usuario_id' => null, 'modulo' => 'roles', 'accion' => 'permisos_aseo_habilitados', 'registro_id' => $rol->id,
                        'valores_anteriores' => json_encode(['permisos' => $anteriores], JSON_THROW_ON_ERROR),
                        'valores_nuevos' => json_encode(['permisos' => $nuevos], JSON_THROW_ON_ERROR),
                        'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('Los accesos de aseo requieren una corrección explícita hacia adelante.');
    }
};
