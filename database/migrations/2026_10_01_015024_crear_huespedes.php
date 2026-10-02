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
        Schema::create('huespedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->text('nombre_busqueda');
            $table->string('telefono', 80)->nullable();
            $table->string('telefono_busqueda', 80)->nullable()->index();
            $table->string('numero_identidad', 80)->nullable();
            $table->string('identidad_busqueda', 160)->nullable()->unique();
            $table->string('nacionalidad', 120)->nullable();
            $table->text('notas')->nullable();
            $table->text('notas_incidentes')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('vip')->default(false);
            $table->boolean('frecuente')->default(false);
            $table->boolean('lista_negra')->default(false);
            $table->foreignId('creado_por')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['deleted_at', 'activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('huespedes');
    }
};
