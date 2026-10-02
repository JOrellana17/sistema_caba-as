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
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('correo')->nullable()->change();
            $table->softDeletes();
        });
        Schema::table('registros_auditoria', function (Blueprint $table) {
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registros_auditoria', function (Blueprint $table) {
            $table->dropColumn(['registro_id', 'valores_anteriores', 'valores_nuevos']);
        });
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        // El correo permanece nullable: no inventar correos al revertir.
    }
};
