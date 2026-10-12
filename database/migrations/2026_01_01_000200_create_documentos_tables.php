<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DOCUMENTOS Y DIRECCIONES (POLIMÓRFICOS)
 *
 * Una sola tabla de documentos sirve a TODAS las entidades del sistema:
 * unidades, contratos, arrendatarios, pólizas, mantenimientos, pagos.
 * Agregar una entidad nueva no requiere migración nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_documento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');                       // "Tarjeta de circulación"
            $table->string('clave')->unique();              // tarjeta_circulacion
            $table->string('aplica_a');                     // App\Models\Unidad
            $table->boolean('es_obligatorio')->default(false);
            $table->boolean('requiere_vigencia')->default(false);
            $table->unsignedInteger('dias_alerta_previa')->default(30);
            $table->boolean('permite_multiples')->default(false); // p.ej. fotos: sí; factura: no
            $table->json('mimes_permitidos')->nullable();        // null = usar default del sistema
            $table->unsignedInteger('tamano_max_mb')->default(10);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['aplica_a', 'activo']);
        });

        Schema::create('documentos', function (Blueprint $table) {
            $table->id();

            // documentable_type + documentable_id
            $table->morphs('documentable');

            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->restrictOnDelete();

            $table->string('nombre_original');
            $table->string('ruta');                        // disco PRIVADO, nunca public
            $table->string('disco')->default('documentos');
            $table->string('mime', 100);
            $table->unsignedBigInteger('tamano');          // bytes
            $table->string('hash', 64)->nullable();        // sha256: detecta duplicados exactos

            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();

            // Versionado: al reemplazar un doc, el anterior queda con vigente = false
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('vigente')->default(true);
            $table->foreignId('reemplaza_a')->nullable()->constrained('documentos')->nullOnDelete();

            $table->text('notas')->nullable();
            $table->foreignId('subido_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fecha_vencimiento', 'vigente']);
            $table->index(['documentable_type', 'documentable_id', 'tipo_documento_id'], 'idx_doc_entidad_tipo');
        });

        /**
         * Direcciones polimórficas: arrendatarios, proveedores, aseguradoras
         * y a futuro clientes de la comercializadora.
         */
        Schema::create('direcciones', function (Blueprint $table) {
            $table->id();
            $table->morphs('direccionable');
            $table->string('tipo')->default('particular');  // fiscal|particular|entrega|resguardo
            $table->string('calle');
            $table->string('numero_exterior')->nullable();
            $table->string('numero_interior')->nullable();
            $table->string('colonia')->nullable();
            $table->string('municipio');
            $table->string('estado');
            $table->string('codigo_postal', 10);
            $table->string('pais')->default('México');
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->text('referencias')->nullable();
            $table->boolean('es_principal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direcciones');
        Schema::dropIfExists('documentos');
        Schema::dropIfExists('tipos_documento');
    }
};
