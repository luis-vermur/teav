<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COTIZACIONES
 *
 * Una sola tabla cubre dos usos, distinguidos por `proposito`:
 *
 *  - analisis_compra: ¿me conviene comprar este auto y rentarlo?
 *    (lo que hacía el Excel, antes de que exista la unidad)
 *  - comercial: propuesta formal a un cliente, que puede volverse contrato
 *
 * El vehículo puede ser una unidad real (unidad_id) o hipotético
 * (submarca_id + año + precios). Por eso ambos son nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();               // COT-2026-0001

            $table->enum('proposito', ['analisis_compra', 'comercial'])
                  ->default('analisis_compra');

            $table->enum('estatus', [
                'borrador', 'enviada', 'aceptada',
                'rechazada', 'vencida', 'convertida',
            ])->default('borrador');

            // ---------- Vehículo ----------
            // Opción A: unidad que ya existe en la flota
            $table->foreignId('unidad_id')->nullable()->constrained('unidades')->nullOnDelete();

            // Opción B: vehículo hipotético, aún no comprado
            $table->foreignId('submarca_id')->nullable()->constrained('submarcas')->nullOnDelete();
            $table->unsignedSmallInteger('anio')->nullable();
            $table->string('version')->nullable();
            $table->string('color_descripcion')->nullable();

            // ---------- Inversión ----------
            $table->decimal('precio_lista', 14, 2)->nullable();
            $table->decimal('precio_adquisicion', 14, 2);
            $table->decimal('gastos_adquisicion', 14, 2)->default(0);
            $table->decimal('inversion_total', 14, 2);

            // ---------- Destinatario (sólo para propósito comercial) ----------
            $table->foreignId('arrendatario_id')->nullable()->constrained('arrendatarios')->nullOnDelete();
            $table->string('prospecto_nombre')->nullable();
            $table->string('prospecto_email')->nullable();
            $table->string('prospecto_telefono')->nullable();
            $table->string('prospecto_empresa')->nullable();

            // ---------- Términos propuestos ----------
            $table->unsignedSmallInteger('plazo_meses')->default(48);
            $table->decimal('renta_sin_iva', 14, 2);
            $table->decimal('tasa_iva', 6, 4)->default(0.16);
            $table->decimal('renta_con_iva', 14, 2);
            $table->decimal('deposito_garantia', 14, 2)->default(0);
            $table->decimal('enganche', 14, 2)->default(0);
            $table->decimal('valor_residual_estimado', 14, 2)->default(0);
            $table->boolean('con_opcion_compra')->default(false);
            $table->unsignedInteger('limite_km_anual')->nullable();

            // ---------- Costos estimados (lo que el Excel no tenía) ----------
            $table->decimal('seguro_anual_estimado', 14, 2)->default(0);
            $table->decimal('mantenimiento_anual_estimado', 14, 2)->default(0);
            $table->decimal('otros_costos_anuales', 14, 2)->default(0);
            $table->unsignedSmallInteger('dias_ociosos_estimados')->default(0);

            // ---------- Resultados calculados (fotografía) ----------
            $table->decimal('renta_total_contrato', 14, 2)->default(0);
            $table->decimal('ingreso_bruto_total', 14, 2)->default(0);
            $table->decimal('egresos_estimados', 14, 2)->default(0);
            $table->decimal('ganancia_neta', 14, 2)->default(0);
            $table->decimal('rendimiento_renta', 12, 6)->default(0);
            $table->decimal('rendimiento_total', 12, 6)->default(0);
            $table->decimal('rendimiento_neto', 12, 6)->default(0);
            $table->decimal('rendimiento_anual', 12, 6)->default(0);
            $table->decimal('punto_equilibrio_meses', 8, 2)->nullable();

            // ---------- Seguimiento ----------
            $table->date('fecha_cotizacion');
            $table->date('vigencia_hasta')->nullable();
            $table->date('fecha_respuesta')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->nullOnDelete();

            $table->text('condiciones')->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['proposito', 'estatus']);
            $table->index(['vigencia_hasta', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
