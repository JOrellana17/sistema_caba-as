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
        Schema::table('cabanas', function (Blueprint $table) {
            $table->json('camas')->nullable();
            $table->boolean('tiene_refrigerador')->nullable();
            $table->boolean('tiene_aire_acondicionado')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('cabanas')->whereNotNull('camas')->orWhereNotNull('tiene_refrigerador')->orWhereNotNull('tiene_aire_acondicionado')->exists()) {
            throw new LogicException('El equipamiento registrado se conserva; utiliza una migración correctiva.');
        }
        Schema::table('cabanas', fn (Blueprint $table) => $table->dropColumn(['camas', 'tiene_refrigerador', 'tiene_aire_acondicionado']));
    }
};
