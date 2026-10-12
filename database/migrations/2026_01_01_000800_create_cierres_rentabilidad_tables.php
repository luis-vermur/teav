<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CIERRE DE CONTRATO Y RENTABILIDAD
 *
 * `analisis_rentabilidad` es la digitalización del Excel, pero con dos
 * diferencias importantes:
 *
 *  1. Se corrige el nombre: lo que el Excel llamaba "Ganancia total" es
 *     ingreso bruto (renta total + venta), sin restar el costo. Aquí se
 *     llama `ingreso_bruto_total`, y se agrega `ganancia_neta` de verdad.
 *  2. Entran los costos operativos reales (mantenimientos, seguros,
 *     deducibles, tenencias), que el Excel no consideraba.
 *
 * Cada fila es una FOTOGRAFÍA fechada, no un cálculo vivo. Así se puede
 * comparar lo que se proyectó al comprar contra lo que realmente pasó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cierres_contrato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();

            $table->enum('tipo_cierre', [
                'termino_natural',
                'venta_arrendatario',
                'venta_tercero',
                'devolucion',
                'renovacion',
                'rescision_anticipada',
                'perdida_total',
            ]);
            $table->date('fecha_cierre');

            $table->unsignedInteger('kilometraje_final')->nullable();
            $table->unsignedInteger('km_excedente')->nullable();
            $table->decimal('cargo_km_excedente', 14, 2)->default(0);

            $table->decimal('costo_acondicionamiento', 14, 2)->default(0);
            $table->decimal('cargo_daños', 14, 2)->default(0);
            $table->decimal('adeudo_pendiente', 14, 2)->default(0);

            // Si hubo venta de la unidad
            $table->decimal('monto_venta', 14, 2)->nullable();
            $table->string('comprador_nombre')->nullable();
            $table->foreignId('comprador_arrendatario_id')->nullable()
                  ->constrained('arrendatarios')->nullOnDelete();

            $table->decimal('deposito_retenido', 14, 2)->default(0);
            $table->decimal('deposito_devuelto', 14, 2)->default(0);

            $table->text('inventario_entrega')->nullable();  // llantas, llaves, herramienta
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('analisis_rentabilidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->nullOnDelete();

            $table->enum('tipo', ['proyeccion', 'real', 'escenario'])->default('proyeccion');
            $table->string('nombre_escenario')->nullable();  // "Compra con descuento agencia"
            $table->date('fecha_calculo');

            // --- Inversión ---
            $table->decimal('precio_lista', 14, 2)->nullable();
            $table->decimal('precio_adquisicion', 14, 2);
            $table->decimal('gastos_adquisicion', 14, 2)->default(0);
            $table->decimal('inversion_total', 14, 2);        // adquisición + gastos

            // --- Ingresos ---
            $table->decimal('renta_con_iva', 14, 2);
            $table->unsignedSmallInteger('plazo_meses');
            $table->decimal('renta_total_contrato', 14, 2);   // renta * plazo
            $table->decimal('valor_venta', 14, 2)->default(0);
            $table->decimal('ingresos_otros', 14, 2)->default(0);  // penalizaciones, km excedente
            $table->decimal('ingreso_bruto_total', 14, 2);    // el "Ganancia total" del Excel

            // --- Egresos (lo que faltaba en el Excel) ---
            $table->decimal('costo_seguros', 14, 2)->default(0);
            $table->decimal('costo_mantenimientos', 14, 2)->default(0);
            $table->decimal('costo_tenencias', 14, 2)->default(0);
            $table->decimal('costo_deducibles', 14, 2)->default(0);
            $table->decimal('costo_otros', 14, 2)->default(0);
            $table->decimal('egresos_totales', 14, 2)->default(0);

            // --- Resultado ---
            $table->decimal('ganancia_neta', 14, 2);          // ingreso bruto - inversión - egresos
            $table->decimal('rendimiento_renta', 12, 6);      // (renta total - inversión) / inversión
            $table->decimal('rendimiento_total', 12, 6);      // (ingreso bruto - inversión) / inversión
            $table->decimal('rendimiento_neto', 12, 6);       // ganancia neta / inversión
            $table->decimal('rendimiento_anual', 12, 6);      // rendimiento / años del contrato

            $table->unsignedSmallInteger('dias_ociosos')->default(0); // sin rentar
            $table->decimal('ocupacion', 8, 4)->nullable();           // fracción de tiempo rentada

            $table->text('supuestos')->nullable();
            $table->foreignId('calculado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unidad_id', 'tipo', 'fecha_calculo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisis_rentabilidad');
        Schema::dropIfExists('cierres_contrato');
    }
};
