<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MÉTRICAS DE RIESGO
 *
 * Con el cliente absorbiendo los costos, los egresos dejaron de explicar
 * por qué el rendimiento real difiere del proyectado. Lo que ahora lo
 * explica es: el auto parado, la cartera que nunca se cobró y haberlo
 * vendido por debajo de lo estimado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analisis_rentabilidad', function (Blueprint $table) {
            $table->decimal('perdida_ociosidad', 14, 2)->default(0);
            $table->decimal('perdida_cartera', 14, 2)->default(0);
            $table->decimal('diferencia_residual', 14, 2)->default(0);
            $table->decimal('rendimiento_proyectado', 12, 6)->nullable();
            $table->decimal('brecha_rendimiento', 12, 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('analisis_rentabilidad', function (Blueprint $table) {
            $table->dropColumn([
                'perdida_ociosidad', 'perdida_cartera', 'diferencia_residual',
                'rendimiento_proyectado', 'brecha_rendimiento',
            ]);
        });
    }
};
