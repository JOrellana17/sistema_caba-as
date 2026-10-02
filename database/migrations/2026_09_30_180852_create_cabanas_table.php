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
        Schema::create('cabanas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->index();
            $table->string('slug', 180)->unique();
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('capacidad_base');
            $table->unsignedSmallInteger('capacidad_maxima');
            $table->decimal('precio_base', 12, 2);
            $table->decimal('precio_persona_adicional', 12, 2);
            $table->string('estado', 30);
            $table->boolean('activa');
            $table->string('imagen_principal')->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('creado_por')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['deleted_at', 'activa', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabanas');
    }
};
