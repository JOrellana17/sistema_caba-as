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
        Schema::create('fuentes_calendario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabana_id')->constrained('cabanas')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('proveedor', 20);
            $table->text('url')->nullable();
            $table->string('interpretacion_fechas', 30);
            $table->string('zona_horaria', 100);
            $table->boolean('activa')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->date('cobertura_desde')->nullable();
            $table->date('cobertura_hasta')->nullable();
            $table->dateTime('ultima_correcta')->nullable();
            $table->dateTime('proxima_consulta')->nullable()->index();
            $table->string('estado', 30)->default('sin_consultar');
            $table->string('error', 200)->nullable();
            $table->text('etag')->nullable();
            $table->string('ultima_modificacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuentes_calendario');
    }
};
