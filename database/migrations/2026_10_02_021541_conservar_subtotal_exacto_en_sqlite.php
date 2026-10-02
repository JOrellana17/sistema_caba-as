<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite trata DECIMAL como afinidad NUMERIC y puede convertirlo en
        // REAL. Una cadena decimal conserva centavos incluso con 16 cifras.
        // Los motores con DECIMAL real conservan su columna numérica.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('reservaciones', fn (Blueprint $table) => $table->string('subtotal', 18)->change());
        }
    }

    public function down(): void
    {
        // Conservar el almacenamiento exacto: una reversión a REAL podría
        // perder centavos de datos existentes. La migración creadora de la
        // tabla sigue controlando su propia reversión.
    }
};
