<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * MANTENIMIENTOS A CARGO DEL CLIENTE
 *
 * Por contrato, el arrendatario absorbe el mantenimiento del vehículo. El
 * default pasa de `arrendadora` a `arrendatario` para que el caso normal
 * no dependa de que alguien se acuerde de cambiarlo.
 *
 * `se_recobra` distingue los dos escenarios que antes se confundían:
 *   - El cliente lleva el auto al taller y paga directo  -> sólo bitácora,
 *     sin efecto en el dinero.
 *   - La arrendadora adelanta el pago y se lo cobra      -> hay que generar
 *     el cargo en el calendario del contrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mantenimientos', function (Blueprint $table) {
            $table->boolean('se_recobra')->default(false);
        });

        // El default del enum. No se tocan las filas ya capturadas: su
        // responsable quedó decidido cuando se registraron.
        DB::statement("ALTER TABLE mantenimientos ALTER COLUMN responsable_costo SET DEFAULT 'arrendatario'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE mantenimientos ALTER COLUMN responsable_costo SET DEFAULT 'arrendadora'");

        Schema::table('mantenimientos', function (Blueprint $table) {
            $table->dropColumn('se_recobra');
        });
    }
};
