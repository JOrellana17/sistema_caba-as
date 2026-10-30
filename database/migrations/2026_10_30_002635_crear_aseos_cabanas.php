<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('aseos_cabanas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabana_id')->constrained('cabanas')->restrictOnDelete();
            $table->foreignId('reservacion_id')->unique()->constrained('reservaciones')->restrictOnDelete();
            $table->dateTime('iniciado_en');
            $table->dateTime('confirmado_en')->nullable();
            $table->foreignId('confirmado_por')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->index(['cabana_id', 'confirmado_en']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('aseos_cabanas')->exists()) {
            throw new LogicException('El historial de aseo se conserva; utiliza una migración correctiva.');
        }
        Schema::dropIfExists('aseos_cabanas');
    }
};
