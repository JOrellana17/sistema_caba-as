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
        Schema::rename('users', 'usuarios');

        Schema::table('usuarios', function (Blueprint $table) {
            $table->renameColumn('name', 'nombre');
            $table->renameColumn('email', 'correo');
            $table->renameColumn('password', 'contrasena');
            $table->renameColumn('email_verified_at', 'correo_verificado_at');
            $table->string('usuario', 100)->nullable()->unique();
            $table->boolean('activo')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropUnique(['usuario']);
            $table->dropColumn(['usuario', 'activo']);
            $table->renameColumn('nombre', 'name');
            $table->renameColumn('correo', 'email');
            $table->renameColumn('contrasena', 'password');
            $table->renameColumn('correo_verificado_at', 'email_verified_at');
        });

        Schema::rename('usuarios', 'users');
    }
};
