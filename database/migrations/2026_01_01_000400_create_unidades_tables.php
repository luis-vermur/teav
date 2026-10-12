<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNIDADES (FLOTILLA)
 *
 * Nota sobre precios: el Excel manejaba "precio base" y "precio descuento".
 * Aquí son `precio_lista` y `precio_adquisicion`. Se agrega `gastos_adquisicion`
 * (placas, gestoría, traslado, accesorios) porque el costo real de poner
 * la unidad a rentar NO es sólo la factura, y ese dato faltaba en el Excel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_interno')->unique();     // TEAV-001
            $table->string('vin', 17)->unique();
            $table->string('placas', 15)->nullable()->index();
            $table->string('numero_motor')->nullable();

            $table->foreignId('submarca_id')->constrained('submarcas')->restrictOnDelete();
            $table->foreignId('color_id')->nullable()->constrained('colores')->nullOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->string('version')->nullable();          // "Advanced 35 TFSI"

            $table->enum('transmision', ['manual', 'automatica', 'cvt', 'dsg'])->nullable();
            $table->enum('combustible', ['gasolina', 'diesel', 'hibrido', 'electrico'])->nullable();
            $table->unsignedTinyInteger('numero_pasajeros')->nullable();

            // --- Adquisición ---
            $table->date('fecha_adquisicion')->nullable();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('factura_folio')->nullable();
            $table->decimal('precio_lista', 14, 2)->nullable();       // "precio base" del Excel
            $table->decimal('precio_adquisicion', 14, 2);             // "precio descuento" del Excel
            $table->decimal('gastos_adquisicion', 14, 2)->default(0);
            $table->enum('forma_adquisicion', ['contado', 'credito', 'arrendamiento_puro'])->default('contado');

            // --- Valor de salida ---
            $table->decimal('valor_residual_estimado', 14, 2)->nullable(); // "venta unidad" del Excel
            $table->decimal('valor_venta_real', 14, 2)->nullable();        // se llena al vender

            // --- Operación ---
            $table->unsignedInteger('kilometraje_inicial')->default(0);
            $table->unsignedInteger('kilometraje_actual')->default(0);
            $table->date('fecha_ultimo_kilometraje')->nullable();

            $table->enum('estatus', [
                'en_transito',   // comprada, aún no disponible
                'disponible',
                'apartada',
                'arrendada',
                'mantenimiento',
                'siniestrada',
                'en_venta',
                'vendida',
                'baja',
            ])->default('disponible');

            $table->string('ubicacion')->nullable();   // dónde está resguardada si no está arrendada
            $table->boolean('tiene_gps')->default(false);
            $table->string('gps_identificador')->nullable();

            $table->text('notas')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estatus', 'anio']);
        });

        /**
         * Bitácora de cambios de estatus. Permite calcular días de ociosidad
         * (unidad disponible sin rentar), que es la métrica que más erosiona
         * el rendimiento real vs. el proyectado.
         */
        Schema::create('unidad_historial_estatus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->string('estatus_anterior')->nullable();
            $table->string('estatus_nuevo');
            $table->timestamp('inicio');
            $table->timestamp('fin')->nullable();
            $table->unsignedInteger('dias')->nullable();   // se calcula al cerrar el periodo
            $table->text('motivo')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unidad_id', 'inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidad_historial_estatus');
        Schema::dropIfExists('unidades');
    }
};
