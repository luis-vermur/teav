<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COBRANZA
 *
 * Modelo contable de tres piezas, no de una:
 *   1. pagos_programados  -> lo que el arrendatario DEBE (calendario)
 *   2. pagos              -> el dinero que ENTRÓ (un depósito, una transferencia)
 *   3. aplicaciones_pago  -> cómo ese dinero se reparte entre lo que se debe
 *
 * Separarlos es lo que permite manejar pagos parciales, un solo depósito que
 * cubre dos mensualidades, y pagos que primero liquidan penalizaciones.
 * Un solo campo "pagado: sí/no" no aguanta la realidad de la cobranza.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_programados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero_exhibicion');   // 1..48
            $table->date('fecha_vencimiento');
            $table->date('fecha_limite');                        // vencimiento + días de gracia

            $table->decimal('monto_sin_iva', 14, 2);
            $table->decimal('tasa_iva', 6, 4);
            $table->decimal('monto_total', 14, 2);
            $table->decimal('monto_aplicado', 14, 2)->default(0);
            $table->decimal('saldo', 14, 2);                     // monto_total - monto_aplicado

            $table->enum('estatus', ['pendiente', 'parcial', 'pagado', 'vencido', 'condonado'])
                  ->default('pendiente');
            $table->date('fecha_liquidacion')->nullable();
            $table->unsignedSmallInteger('dias_atraso')->default(0);

            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['contrato_id', 'numero_exhibicion']);
            $table->index(['estatus', 'fecha_vencimiento']);
        });

        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();                   // PAG-2026-000001
            $table->foreignId('contrato_id')->constrained('contratos')->restrictOnDelete();
            $table->foreignId('arrendatario_id')->constrained('arrendatarios')->restrictOnDelete();

            $table->date('fecha_pago');
            $table->decimal('monto', 14, 2);
            $table->decimal('monto_aplicado', 14, 2)->default(0);
            $table->decimal('saldo_a_favor', 14, 2)->default(0); // lo que sobró sin aplicar

            $table->enum('metodo_pago', [
                'transferencia', 'deposito', 'efectivo', 'cheque', 'tarjeta', 'domiciliacion', 'otro',
            ]);
            $table->string('referencia')->nullable();            // clave de rastreo
            $table->string('cuenta_destino')->nullable();
            $table->string('banco_origen')->nullable();

            $table->enum('estatus', ['registrado', 'confirmado', 'aplicado', 'reversado', 'rechazado'])
                  ->default('registrado');
            $table->text('motivo_reverso')->nullable();

            $table->text('notas')->nullable();
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contrato_id', 'fecha_pago']);
            $table->index(['estatus', 'fecha_pago']);
        });

        Schema::create('penalizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->foreignId('pago_programado_id')->nullable()->constrained('pagos_programados')->nullOnDelete();

            $table->enum('tipo', ['mora', 'km_excedente', 'daños', 'documentos', 'devolucion_tardia', 'otro']);
            $table->date('fecha_generacion');
            $table->unsignedSmallInteger('dias_atraso')->nullable();
            $table->decimal('base_calculo', 14, 2)->nullable();
            $table->decimal('monto', 14, 2);
            $table->decimal('monto_aplicado', 14, 2)->default(0);
            $table->decimal('saldo', 14, 2);

            $table->enum('estatus', ['pendiente', 'parcial', 'pagada', 'condonada'])->default('pendiente');
            $table->text('descripcion')->nullable();
            $table->text('motivo_condonacion')->nullable();
            $table->foreignId('condonada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_condonacion')->nullable();

            $table->boolean('generada_automaticamente')->default(true);
            $table->timestamps();

            $table->index(['contrato_id', 'estatus']);
        });

        /**
         * Reparte un pago entre exhibiciones y penalizaciones.
         * Un pago puede tener varias aplicaciones; una exhibición puede recibir varias.
         */
        Schema::create('aplicaciones_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->foreignId('pago_programado_id')->nullable()->constrained('pagos_programados')->cascadeOnDelete();
            $table->foreignId('penalizacion_id')->nullable()->constrained('penalizaciones')->cascadeOnDelete();
            $table->enum('concepto', ['renta', 'penalizacion', 'deposito', 'enganche', 'otro'])->default('renta');
            $table->decimal('monto', 14, 2);
            $table->timestamps();

            $table->index(['pago_id']);
        });

        /**
         * Movimientos del depósito en garantía: recepción, retenciones al cierre
         * y devolución. Sin esta tabla no se puede demostrar por qué se devolvió
         * menos de lo que se recibió.
         */
        Schema::create('movimientos_deposito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->enum('tipo', ['recepcion', 'incremento', 'retencion', 'devolucion', 'aplicacion_renta']);
            $table->date('fecha');
            $table->decimal('monto', 14, 2);          // positivo entra, negativo sale
            $table->decimal('saldo_resultante', 14, 2);
            $table->text('motivo')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contrato_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_deposito');
        Schema::dropIfExists('aplicaciones_pago');
        Schema::dropIfExists('penalizaciones');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('pagos_programados');
    }
};
