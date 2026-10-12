<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los mismos cargos al cliente que ya tiene `contratos`, ahora en la
 * cotización.
 *
 * Hacían falta para poder imprimir el documento comercial: sin fecha de
 * inicio ni día de corte no se puede calcular el primer mes proporcional,
 * y sin gastos_iniciales no hay línea de "placas y gestoría".
 *
 * Se usan los MISMOS nombres que en `contratos` a propósito: así al
 * convertir la cotización en contrato los valores se copian tal cual, sin
 * traducciones de por medio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            // Para el prorrateo del primer mes.
            $table->date('fecha_inicio_estimada')->nullable();
            $table->unsignedTinyInteger('dia_corte')->default(1);
            $table->boolean('prorratear_primer_mes')->default(true);

            // Seguro que se le recobra al cliente.
            $table->boolean('cobra_seguro')->default(true);
            $table->decimal('monto_seguro_anual', 14, 2)->default(0);

            // Placas, gestoría, tenencia.
            $table->decimal('gastos_iniciales', 14, 2)->default(0);
            $table->string('concepto_gastos_iniciales')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_inicio_estimada',
                'dia_corte',
                'prorratear_primer_mes',
                'cobra_seguro',
                'monto_seguro_anual',
                'gastos_iniciales',
                'concepto_gastos_iniciales',
            ]);
        });
    }
};
