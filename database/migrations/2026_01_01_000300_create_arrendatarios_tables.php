<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARRENDATARIOS
 *
 * Tabla única con discriminador `tipo_persona`. Los campos de persona física
 * y de persona moral conviven como nullable; la validación de cuáles son
 * obligatorios vive en el FormRequest, no en la base de datos.
 * Se prefirió esto sobre dos tablas separadas porque el 80% de los campos
 * son comunes y los contratos apuntan a una sola llave foránea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arrendatarios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();             // ARR-2026-0001
            $table->enum('tipo_persona', ['fisica', 'moral']);

            // --- Comunes ---
            $table->string('rfc', 13)->nullable()->index();
            $table->string('regimen_fiscal', 10)->nullable(); // clave SAT, para cuando entre CFDI
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('telefono_alterno')->nullable();

            // --- Persona física ---
            $table->string('nombre')->nullable();
            $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable();
            $table->string('curp', 18)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('nacionalidad')->nullable();
            $table->string('ocupacion')->nullable();
            $table->string('licencia_numero')->nullable();
            $table->date('licencia_vigencia')->nullable();

            // --- Persona moral ---
            $table->string('razon_social')->nullable();
            $table->string('nombre_comercial')->nullable();
            $table->date('fecha_constitucion')->nullable();
            $table->string('acta_constitutiva_numero')->nullable();
            $table->string('representante_legal_nombre')->nullable();
            $table->string('representante_legal_rfc', 13)->nullable();
            $table->string('representante_legal_curp', 18)->nullable();
            $table->string('representante_legal_telefono')->nullable();
            $table->string('representante_legal_email')->nullable();

            // --- Control interno ---
            $table->enum('estatus', [
                'prospecto', 'en_validacion', 'activo', 'suspendido', 'lista_negra', 'inactivo',
            ])->default('prospecto');
            $table->unsignedTinyInteger('calificacion_interna')->nullable(); // 1-5
            $table->decimal('ingreso_mensual_declarado', 14, 2)->nullable();
            $table->decimal('limite_credito', 14, 2)->nullable();
            $table->text('motivo_estatus')->nullable();   // por qué está suspendido / en lista negra
            $table->text('notas')->nullable();

            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tipo_persona', 'estatus']);
        });

        Schema::create('referencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arrendatario_id')->constrained('arrendatarios')->cascadeOnDelete();
            $table->enum('tipo', ['personal', 'familiar', 'laboral', 'comercial', 'bancaria']);
            $table->string('nombre');
            $table->string('relacion')->nullable();
            $table->string('telefono');
            $table->string('email')->nullable();
            $table->string('empresa')->nullable();
            $table->boolean('verificada')->default(false);
            $table->date('fecha_verificacion')->nullable();
            $table->foreignId('verificada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        /**
         * Contactos adicionales, sobre todo para personas morales:
         * quién autoriza pagos, quién recibe la unidad, quién firma.
         */
        Schema::create('contactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arrendatario_id')->constrained('arrendatarios')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('puesto')->nullable();
            $table->string('rol')->nullable();      // firmante|pagos|operativo|conductor
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->boolean('es_principal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactos');
        Schema::dropIfExists('referencias');
        Schema::dropIfExists('arrendatarios');
    }
};
