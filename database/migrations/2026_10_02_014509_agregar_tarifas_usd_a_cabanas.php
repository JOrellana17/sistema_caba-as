<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabanas', function (Blueprint $table) {
            $table->decimal('precio_base_usd', 12, 2)->nullable();
            $table->decimal('precio_persona_adicional_usd', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cabanas', fn (Blueprint $table) => $table->dropColumn(['precio_base_usd', 'precio_persona_adicional_usd']));
    }
};
