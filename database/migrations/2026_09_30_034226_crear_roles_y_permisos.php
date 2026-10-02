<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('codigo', 80)->unique();
            $table->string('descripcion', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 120)->unique();
            $table->string('descripcion', 500)->nullable();
            $table->boolean('protegido')->default(false);
            $table->timestamps();
        });
        Schema::create('rol_usuario', function (Blueprint $table) {
            $table->foreignId('rol_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->primary(['rol_id', 'usuario_id']);
            $table->index('usuario_id');
        });
        Schema::create('permiso_rol', function (Blueprint $table) {
            $table->foreignId('permiso_id')->constrained('permisos')->restrictOnDelete();
            $table->foreignId('rol_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['permiso_id', 'rol_id']);
            $table->index('rol_id');
        });
        Schema::create('permiso_usuario', function (Blueprint $table) {
            $table->foreignId('permiso_id')->constrained('permisos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->primary(['permiso_id', 'usuario_id']);
            $table->index('usuario_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permiso_usuario');
        Schema::dropIfExists('permiso_rol');
        Schema::dropIfExists('rol_usuario');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('roles');
    }
};
