<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservaciones', function (Blueprint $table) {
            $table->index(['estado', 'salida_prevista'], 'reservaciones_finalizacion_indice');
        });
    }

    public function down(): void
    {
        Schema::table('reservaciones', function (Blueprint $table) {
            $table->dropIndex('reservaciones_finalizacion_indice');
        });
    }
};
