<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo inicial de tipos de documento por entidad.
 * `requiere_vigencia = true` es lo que dispara las alertas de vencimiento.
 */
class TiposDocumentoSeeder extends Seeder
{
    public function run(): void
    {
        $ahora = now();

        $tipos = [
            // ---------- UNIDAD ----------
            ['Factura de origen',        'factura_origen',        'App\Models\Unidad', true,  false, false],
            ['Tarjeta de circulación',   'tarjeta_circulacion',   'App\Models\Unidad', true,  true,  false],
            ['Pedimento de importación', 'pedimento',             'App\Models\Unidad', false, false, false],
            ['Verificación vehicular',   'verificacion',          'App\Models\Unidad', false, true,  true],
            ['Comprobante de tenencia',  'tenencia',              'App\Models\Unidad', false, true,  true],
            ['Fotografías de la unidad', 'fotos_unidad',          'App\Models\Unidad', false, false, true],

            // ---------- ARRENDATARIO (persona física) ----------
            ['Identificación oficial',   'ine',                   'App\Models\Arrendatario', true,  true,  false],
            ['Comprobante de domicilio', 'comprobante_domicilio', 'App\Models\Arrendatario', true,  false, false],
            ['Constancia de situación fiscal', 'csf',             'App\Models\Arrendatario', true,  false, false],
            ['Licencia de conducir',     'licencia',              'App\Models\Arrendatario', false, true,  false],
            ['Comprobante de ingresos',  'comprobante_ingresos',  'App\Models\Arrendatario', false, false, true],

            // ---------- ARRENDATARIO (persona moral) ----------
            ['Acta constitutiva',        'acta_constitutiva',     'App\Models\Arrendatario', false, false, false],
            ['Poder del representante',  'poder_representante',   'App\Models\Arrendatario', false, false, false],
            ['INE del representante',    'ine_representante',     'App\Models\Arrendatario', false, true,  false],

            // ---------- CONTRATO ----------
            ['Contrato firmado',         'contrato_firmado',      'App\Models\Contrato', true,  false, false],
            ['Pagaré',                   'pagare',                'App\Models\Contrato', false, false, true],
            ['Carta responsiva',         'carta_responsiva',      'App\Models\Contrato', false, false, false],
            ['Acta de entrega',          'acta_entrega',          'App\Models\Contrato', true,  false, false],
            ['Acta de devolución',       'acta_devolucion',       'App\Models\Contrato', false, false, false],

            // ---------- PÓLIZA ----------
            ['Carátula de póliza',       'caratula_poliza',       'App\Models\PolizaSeguro', true,  true,  false],
            ['Comprobante de pago de prima', 'pago_prima',        'App\Models\PolizaSeguro', false, false, true],

            // ---------- MANTENIMIENTO ----------
            ['Factura de servicio',      'factura_servicio',      'App\Models\Mantenimiento', false, false, true],
            ['Orden de trabajo',         'orden_trabajo',         'App\Models\Mantenimiento', false, false, true],

            // ---------- PAGO ----------
            ['Comprobante de pago',      'comprobante_pago',      'App\Models\Pago', true, false, true],
        ];

        $filas = [];
        foreach ($tipos as $i => [$nombre, $clave, $aplicaA, $obligatorio, $vigencia, $multiples]) {
            $filas[] = [
                'nombre'            => $nombre,
                'clave'             => $clave,
                'aplica_a'          => $aplicaA,
                'es_obligatorio'    => $obligatorio,
                'requiere_vigencia' => $vigencia,
                'dias_alerta_previa'=> 30,
                'permite_multiples' => $multiples,
                'mimes_permitidos'  => json_encode(['application/pdf', 'image/jpeg', 'image/png']),
                // Los PDF escaneados a color rebasan 10 MB con facilidad.
                'tamano_max_mb'     => 30,
                'orden'             => $i,
                'activo'            => true,
                'created_at'        => $ahora,
                'updated_at'        => $ahora,
            ];
        }

        DB::table('tipos_documento')->insert($filas);
    }
}
