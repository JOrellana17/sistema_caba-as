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
        Schema::create('enlaces_calendario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabana_id')->nullable()->constrained('cabanas')->restrictOnDelete();
            $table->string('destino', 30);
            $table->char('token_hash', 64)->unique();
            $table->date('desde');
            $table->date('hasta');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('sincronizaciones_calendario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuente_id')->constrained('fuentes_calendario')->restrictOnDelete();
            $table->string('estado', 30);
            $table->boolean('completa')->default(false);
            $table->unsignedInteger('procesados')->default(0);
            $table->unsignedInteger('retirados')->default(0);
            $table->string('error', 200)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sincronizaciones_calendario');
        Schema::dropIfExists('enlaces_calendario');
    }
};
