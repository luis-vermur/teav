<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CARGOS AL CLIENTE ADEMÁS DE LA RENTA
 *
 * En este arrendamiento el seguro y los gastos de gestoría/placas los paga
 * el cliente: la arrendadora los desembolsa y los recobra. Por eso el
 * calendario deja de ser sólo rentas y pasa a tener conceptos distintos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_programados', function (Blueprint $table) {
            // renta | renta_proporcional | seguro | gastos_iniciales | otro
            $table->string('concepto')->default('renta')->after('numero_exhibicion');
            $table->text('descripcion')->nullable()->after('concepto');

            $table->index(['contrato_id', 'concepto']);
        });

        /**
         * El único viejo era (contrato_id, numero_exhibicion). Ahora un mismo
         * número de exhibición puede tener varias filas —la renta del mes y el
         * seguro de ese mes— así que el concepto entra a formar parte de la
         * llave. Sin este cambio, generar el calendario reventaría.
         */
        Schema::table('pagos_programados', function (Blueprint $table) {
            $table->dropUnique('pagos_programados_contrato_id_numero_exhibicion_unique');
            $table->unique(['contrato_id', 'numero_exhibicion', 'concepto']);
        });

        Schema::table('contratos', function (Blueprint $table) {
            $table->boolean('prorratear_primer_mes')->default(true);
            $table->boolean('cobra_seguro')->default(true);
            $table->decimal('monto_seguro_anual', 14, 2)->default(0);
            $table->decimal('gastos_iniciales', 14, 2)->default(0);
            $table->string('concepto_gastos_iniciales')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn([
                'prorratear_primer_mes',
                'cobra_seguro',
                'monto_seguro_anual',
                'gastos_iniciales',
                'concepto_gastos_iniciales',
            ]);
        });

        Schema::table('pagos_programados', function (Blueprint $table) {
            $table->dropUnique(['contrato_id', 'numero_exhibicion', 'concepto']);
            $table->unique(['contrato_id', 'numero_exhibicion']);
            $table->dropIndex(['contrato_id', 'concepto']);
            $table->dropColumn(['concepto', 'descripcion']);
        });
    }
};
