<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ÍNDICES EN LAS LLAVES FORÁNEAS
 *
 * PostgreSQL crea índice para la llave primaria, pero NO para las foráneas
 * (a diferencia de MySQL). Sin ellos, cada `whereHas`, cada carga de
 * relación y cada borrado en cascada recorre la tabla hija completa.
 *
 * Con decenas de registros no se nota; con miles de contratos y sus
 * exhibiciones —48 por contrato— sí.
 *
 * Se usa CREATE INDEX IF NOT EXISTS y CONCURRENTLY no, a propósito: el
 * volumen actual es pequeño y CONCURRENTLY no puede correr dentro de una
 * transacción, que es como Laravel ejecuta las migraciones.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string}> */
    protected array $indices = [
            ['analisis_rentabilidad', 'calculado_por'],
            ['analisis_rentabilidad', 'contrato_id'],
            ['aplicaciones_pago', 'pago_programado_id'],
            ['aplicaciones_pago', 'penalizacion_id'],
            ['arrendatarios', 'registrado_por'],
            ['cierres_contrato', 'comprador_arrendatario_id'],
            ['cierres_contrato', 'contrato_id'],
            ['cierres_contrato', 'registrado_por'],
            ['contactos', 'arrendatario_id'],
            ['contratos', 'registrado_por'],
            ['contratos', 'renovacion_de'],
            ['contratos', 'unidad_id'],
            ['cotizaciones', 'arrendatario_id'],
            ['cotizaciones', 'contrato_id'],
            ['cotizaciones', 'creada_por'],
            ['cotizaciones', 'submarca_id'],
            ['cotizaciones', 'unidad_id'],
            ['documentos', 'reemplaza_a'],
            ['documentos', 'subido_por'],
            ['documentos', 'tipo_documento_id'],
            ['exports', 'user_id'],
            ['failed_import_rows', 'import_id'],
            ['imports', 'user_id'],
            ['mantenimientos', 'contrato_id'],
            ['mantenimientos', 'proveedor_id'],
            ['mantenimientos', 'registrado_por'],
            ['mantenimientos', 'tipo_mantenimiento_id'],
            ['movimientos_deposito', 'registrado_por'],
            ['pagos', 'arrendatario_id'],
            ['pagos', 'confirmado_por'],
            ['pagos', 'registrado_por'],
            ['penalizaciones', 'condonada_por'],
            ['penalizaciones', 'pago_programado_id'],
            ['polizas_seguro', 'unidad_id'],
            ['referencias', 'arrendatario_id'],
            ['referencias', 'verificada_por'],
            ['role_has_permissions', 'role_id'],
            ['siniestros', 'arrendatario_id'],
            ['siniestros', 'contrato_id'],
            ['siniestros', 'poliza_seguro_id'],
            ['unidad_historial_estatus', 'registrado_por'],
            ['unidades', 'color_id'],
            ['unidades', 'proveedor_id'],
            ['unidades', 'registrado_por'],
            ['unidades', 'submarca_id'],
    ];

    public function up(): void
    {
        foreach ($this->indices as [$tabla, $columna]) {
            $nombre = "idx_{$tabla}_{$columna}";
            DB::statement("CREATE INDEX IF NOT EXISTS \"{$nombre}\" ON \"{$tabla}\" (\"{$columna}\")");
        }
    }

    public function down(): void
    {
        foreach ($this->indices as [$tabla, $columna]) {
            DB::statement("DROP INDEX IF EXISTS \"idx_{$tabla}_{$columna}\"");
        }
    }
};
