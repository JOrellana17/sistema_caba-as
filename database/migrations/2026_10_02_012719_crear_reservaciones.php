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
        Schema::create('reservaciones', function (Blueprint $table) {
            $table->id();
            $table->uuid('codigo')->unique();
            $table->foreignId('cabana_id')->constrained('cabanas')->restrictOnDelete();
            $table->foreignId('huesped_id')->constrained('huespedes')->restrictOnDelete();
            $table->date('fecha_entrada');
            $table->date('fecha_salida');
            $table->dateTime('entrada_prevista');
            $table->dateTime('salida_prevista');
            $table->unsignedSmallInteger('cantidad_huespedes');
            $table->string('estado', 30);
            $table->unsignedSmallInteger('capacidad_base_aplicada');
            $table->unsignedInteger('noches');
            $table->unsignedSmallInteger('personas_adicionales');
            $table->decimal('precio_base', 12, 2);
            $table->decimal('precio_persona_adicional', 12, 2);
            $table->decimal('subtotal', 16, 2);
            $table->char('moneda', 3);
            $table->text('notas')->nullable();
            $table->timestamp('fecha_confirmacion')->nullable();
            $table->timestamp('fecha_entrada_registrada')->nullable();
            $table->timestamp('fecha_salida_registrada')->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();
            $table->timestamp('fecha_no_presentado')->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->foreignId('creado_por')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('cancelado_por')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['cabana_id', 'estado', 'entrada_prevista', 'salida_prevista'], 'reservaciones_disponibilidad_indice');
            $table->index(['huesped_id', 'fecha_entrada']);
            $table->index(['estado', 'fecha_entrada']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservaciones');
    }
};
