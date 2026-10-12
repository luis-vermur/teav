<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CONTRATOS DE ARRENDAMIENTO
 *
 * Pieza central del sistema. Al pasar de 'borrador' a 'vigente' debe
 * generar automáticamente el calendario de pagos (pagos_programados)
 * y mover la unidad a estatus 'arrendada'.
 *
 * Sobre el IVA: se guarda `renta_sin_iva` + `tasa_iva` + `renta_con_iva`.
 * El tercero es redundante a propósito: el negocio piensa en "renta con IVA"
 * (así estaba el Excel) y guardarlo evita discrepancias de redondeo entre
 * lo pactado en papel y lo que calcule el sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();               // CTO-2026-0001
            $table->foreignId('unidad_id')->constrained('unidades')->restrictOnDelete();
            $table->foreignId('arrendatario_id')->constrained('arrendatarios')->restrictOnDelete();

            // --- Vigencia ---
            $table->date('fecha_firma')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedSmallInteger('plazo_meses');     // 48 en el Excel

            // --- Renta ---
            $table->decimal('renta_sin_iva', 14, 2);
            $table->decimal('tasa_iva', 6, 4)->default(0.16);
            $table->decimal('renta_con_iva', 14, 2);
            $table->unsignedTinyInteger('dia_corte')->default(1);   // día del mes en que vence
            $table->enum('periodicidad', ['mensual', 'quincenal', 'semanal'])->default('mensual');

            // --- Dinero de entrada ---
            $table->decimal('deposito_garantia', 14, 2)->default(0);
            $table->decimal('enganche', 14, 2)->default(0);
            $table->decimal('otros_cargos_iniciales', 14, 2)->default(0);
            $table->text('concepto_otros_cargos')->nullable();

            // --- Penalización por atraso ---
            $table->unsignedTinyInteger('dias_gracia')->default(0);
            $table->enum('tipo_penalizacion', ['ninguna', 'porcentaje_mensual', 'monto_fijo_diario', 'porcentaje_unico'])
                  ->default('porcentaje_mensual');
            $table->decimal('valor_penalizacion', 10, 4)->default(0);
            // porcentaje_mensual -> fracción mensual (0.05 = 5% mensual sobre saldo vencido)
            // monto_fijo_diario  -> pesos por día de atraso
            // porcentaje_unico   -> fracción única sobre la exhibición vencida
            $table->decimal('tope_penalizacion', 14, 2)->nullable();

            // --- Kilometraje ---
            $table->unsignedInteger('kilometraje_inicial')->nullable();
            $table->unsignedInteger('limite_km_anual')->nullable();
            $table->decimal('costo_km_excedente', 10, 2)->nullable();

            // --- Salida ---
            $table->decimal('valor_residual_pactado', 14, 2)->nullable(); // opción de compra
            $table->boolean('con_opcion_compra')->default(false);

            $table->enum('estatus', [
                'borrador',
                'por_firmar',
                'vigente',
                'en_incumplimiento',
                'por_vencer',
                'finalizado',
                'rescindido',
                'cancelado',
            ])->default('borrador');

            $table->foreignId('renovacion_de')->nullable()->constrained('contratos')->nullOnDelete();
            $table->text('clausulas_especiales')->nullable();
            $table->text('notas')->nullable();

            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estatus', 'fecha_fin']);
            $table->index(['arrendatario_id', 'estatus']);
        });

        // Llaves foráneas diferidas desde migraciones anteriores
        Schema::table('mantenimientos', function (Blueprint $table) {
            $table->foreign('contrato_id')->references('id')->on('contratos')->nullOnDelete();
        });

        Schema::table('siniestros', function (Blueprint $table) {
            $table->foreign('contrato_id')->references('id')->on('contratos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('siniestros', fn (Blueprint $t) => $t->dropForeign(['contrato_id']));
        Schema::table('mantenimientos', fn (Blueprint $t) => $t->dropForeign(['contrato_id']));
        Schema::dropIfExists('contratos');
    }
};
