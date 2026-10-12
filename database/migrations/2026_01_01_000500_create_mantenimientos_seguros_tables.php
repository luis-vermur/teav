<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MANTENIMIENTOS Y SEGUROS
 *
 * Aquí es donde las "Notas" del Excel (fecha del último cambio de llantas,
 * último servicio) dejan de ser texto suelto y se vuelven datos con
 * costo asociado, que alimentan el rendimiento REAL de cada unidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mantenimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('tipo_mantenimiento_id')->constrained('tipos_mantenimiento')->restrictOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();

            // Si se hizo durante un arrendamiento, se liga para saber a quién imputar
            $table->foreignId('contrato_id')->nullable();

            $table->date('fecha');
            $table->unsignedInteger('kilometraje')->nullable();
            $table->decimal('costo', 14, 2)->default(0);
            $table->decimal('costo_iva', 14, 2)->default(0);
            $table->enum('responsable_costo', ['arrendadora', 'arrendatario', 'seguro', 'garantia'])
                  ->default('arrendadora');

            // Próximo mantenimiento: puede venir del catálogo o fijarse a mano
            $table->date('proximo_fecha')->nullable();
            $table->unsignedInteger('proximo_kilometraje')->nullable();

            $table->string('folio_taller')->nullable();
            $table->text('descripcion')->nullable();
            $table->text('refacciones')->nullable();
            $table->enum('estatus', ['programado', 'en_proceso', 'completado', 'cancelado'])
                  ->default('completado');

            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['unidad_id', 'fecha']);
            $table->index(['proximo_fecha', 'estatus']);
        });

        Schema::create('polizas_seguro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('aseguradora_id')->constrained('aseguradoras')->restrictOnDelete();

            $table->string('numero_poliza');
            $table->string('tipo_cobertura');                 // amplia|limitada|rc
            $table->date('vigencia_inicio');
            $table->date('vigencia_fin');

            $table->decimal('prima_total', 14, 2);
            $table->decimal('suma_asegurada', 14, 2)->nullable();
            $table->decimal('deducible_danos', 8, 4)->nullable();  // fracción: 0.05 = 5%
            $table->decimal('deducible_robo', 8, 4)->nullable();

            $table->enum('forma_pago', ['contado', 'semestral', 'trimestral', 'mensual'])->default('contado');
            $table->enum('estatus', ['vigente', 'por_vencer', 'vencida', 'cancelada'])->default('vigente');

            $table->string('conductor_designado')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['aseguradora_id', 'numero_poliza']);
            $table->index(['vigencia_fin', 'estatus']);
        });

        Schema::create('siniestros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('poliza_seguro_id')->nullable()->constrained('polizas_seguro')->nullOnDelete();
            $table->foreignId('contrato_id')->nullable();
            $table->foreignId('arrendatario_id')->nullable()->constrained('arrendatarios')->nullOnDelete();

            $table->string('numero_reporte')->nullable();
            $table->dateTime('fecha_siniestro');
            $table->enum('tipo', ['colision', 'robo_total', 'robo_parcial', 'cristales', 'daños_terceros', 'otro']);
            $table->string('lugar')->nullable();
            $table->text('descripcion');

            $table->decimal('monto_estimado', 14, 2)->nullable();
            $table->decimal('deducible_pagado', 14, 2)->nullable();
            $table->decimal('monto_recuperado', 14, 2)->nullable();
            $table->enum('responsable_deducible', ['arrendadora', 'arrendatario', 'tercero'])->nullable();

            $table->enum('estatus', ['reportado', 'en_valuacion', 'en_reparacion', 'cerrado', 'perdida_total'])
                  ->default('reportado');
            $table->date('fecha_cierre')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['unidad_id', 'fecha_siniestro']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siniestros');
        Schema::dropIfExists('polizas_seguro');
        Schema::dropIfExists('mantenimientos');
    }
};
