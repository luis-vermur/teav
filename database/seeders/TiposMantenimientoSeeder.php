<?php

namespace Database\Seeders;

use App\Models\TipoMantenimiento;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CATÁLOGO DE TIPOS DE SERVICIO
 *
 * De dónde salen los datos: los servicios habituales de una flota de
 * arrendamiento en México, con la periodicidad que suelen manejar las
 * agencias.
 *
 * `periodicidad_meses` y `periodicidad_km` alimentan el cálculo del
 * próximo servicio. Lo que ocurra primero es lo que manda.
 *
 * Este catálogo crece: el negocio inventa servicios nuevos (polarizado,
 * GPS, pulido). Se pueden dar de alta desde el + del formulario de
 * servicio, no hace falta volver a sembrar.
 */
class TiposMantenimientoSeeder extends Seeder
{
    public function run(): void
    {
        // [nombre, clave, categoría, meses, km]
        $tipos = [
            // ---------- Preventivos: los que se programan ----------
            ['Servicio menor',           'servicio_menor',      'preventivo',  6,  10000],
            ['Servicio mayor',           'servicio_mayor',      'preventivo', 12,  20000],
            ['Cambio de aceite',         'cambio_aceite',       'preventivo',  6,  10000],
            ['Rotación de llantas',      'rotacion_llantas',    'preventivo',  6,  10000],
            ['Alineación y balanceo',    'alineacion_balanceo', 'preventivo',  6,  10000],
            ['Cambio de llantas',        'cambio_llantas',      'preventivo', null, 50000],
            ['Cambio de balatas',        'cambio_balatas',      'preventivo', null, 40000],
            ['Cambio de batería',        'cambio_bateria',      'preventivo', 24,  null],
            ['Afinación',                'afinacion',           'preventivo', 12,  20000],

            // ---------- Obligatorios: los exige la autoridad ----------
            ['Verificación vehicular',   'verificacion',        'obligatorio', 6,  null],
            ['Pago de tenencia',         'tenencia',            'obligatorio', 12, null],

            // ---------- Correctivos: no se programan, ocurren ----------
            ['Reparación de motor',      'reparacion_motor',    'correctivo', null, null],
            ['Reparación de transmisión','reparacion_transmision', 'correctivo', null, null],
            ['Reparación eléctrica',     'reparacion_electrica', 'correctivo', null, null],
            ['Hojalatería y pintura',    'hojalateria_pintura', 'correctivo', null, null],
            ['Cambio de cristales',      'cambio_cristales',    'correctivo', null, null],
            ['Reparación de clima',      'reparacion_clima',    'correctivo', null, null],

            // ---------- Otros ----------
            ['Lavado y detallado',       'lavado_detallado',    'preventivo', null, null],
            ['Instalación de GPS',       'instalacion_gps',     'preventivo', null, null],
        ];

        $creados = 0;
        $existentes = 0;

        // Una transacción para los ~19: agrupa los writes sin perder la
        // idempotencia, que la garantiza el único de `clave`.
        DB::transaction(function () use ($tipos, &$creados, &$existentes) {
            foreach ($tipos as [$nombre, $clave, $categoria, $meses, $km]) {
                $tipo = TipoMantenimiento::firstOrCreate(
                    ['clave' => $clave],
                    [
                        'nombre'             => $nombre,
                        'categoria'          => $categoria,
                        'periodicidad_meses' => $meses,
                        'periodicidad_km'    => $km,
                        // Los correctivos no se anticipan: no generan alerta.
                        'genera_alerta'      => $categoria !== 'correctivo',
                        'activo'             => true,
                    ],
                );

                $tipo->wasRecentlyCreated ? $creados++ : $existentes++;
            }
        });

        $this->command->info("Tipos de servicio: {$creados} creados, {$existentes} ya existían.");
    }
}
