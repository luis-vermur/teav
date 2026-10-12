<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CATÁLOGOS BASE
 * Tablas de referencia que alimentan al resto del sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marcas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('pais_origen')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('submarcas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('segmento')->nullable(); // sedán, SUV, pickup...
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['marca_id', 'nombre']);
        });

        Schema::create('colores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('hex', 7)->nullable(); // para pintar el chip en la UI
            $table->timestamps();
        });

        Schema::create('aseguradoras', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('rfc', 13)->nullable();
            $table->string('telefono_siniestros')->nullable();
            $table->string('contacto_nombre')->nullable();
            $table->string('contacto_email')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        /**
         * Proveedores: agencias donde se compran las unidades, talleres,
         * verificentros, llanteras, gestorías. Un mismo proveedor puede ser varios.
         */
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('rfc', 13)->nullable();
            $table->json('tipos');  // ["agencia","taller","llantera","gestoria"]
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('contacto_nombre')->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        /**
         * Tipos de mantenimiento. Se cataloga en lugar de usar un enum fijo
         * porque el negocio va a inventar tipos nuevos (pulido, polarizado, GPS...).
         */
        Schema::create('tipos_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');                                  // "Servicio mayor"
            $table->string('clave')->unique();                         // servicio_mayor
            $table->string('categoria');                               // preventivo|correctivo|obligatorio
            $table->unsignedInteger('periodicidad_meses')->nullable(); // para calcular el próximo
            $table->unsignedInteger('periodicidad_km')->nullable();
            $table->boolean('genera_alerta')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_mantenimiento');
        Schema::dropIfExists('proveedores');
        Schema::dropIfExists('aseguradoras');
        Schema::dropIfExists('colores');
        Schema::dropIfExists('submarcas');
        Schema::dropIfExists('marcas');
    }
};
