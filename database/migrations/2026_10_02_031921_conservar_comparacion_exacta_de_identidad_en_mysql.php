<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La identidad ya está normalizada en mayúsculas sin separadores.
        // Ignorar tildes solo corresponde al nombre, no al documento.
        // SQLite compara en binario por defecto; la intercalación es de MySQL.
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('huespedes', function (Blueprint $table) {
                $table->string('identidad_busqueda', 160)->nullable()->collation('utf8mb4_bin')->change();
            });
        }
    }

    public function down(): void
    {
        // Puede rechazarse si hay documentos que solo se distinguen por tildes.
        // No elimina ni transforma las identidades para forzar la reversión.
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('huespedes', function (Blueprint $table) {
                $table->string('identidad_busqueda', 160)->nullable()->collation('utf8mb4_unicode_ci')->change();
            });
        }
    }
};
