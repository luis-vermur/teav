<?php

namespace Database\Seeders;

use App\Models\Aseguradora;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CATÁLOGO DE ASEGURADORAS
 *
 * De dónde salen los datos:
 * Compañías con operación de seguro de automóvil en México, entre las de
 * mayor participación en el ramo. Es el universo del que una arrendadora
 * elige al contratar la póliza de la flotilla.
 *
 * ┌──────────────────────────────────────────────────────────────────────┐
 * │ ADVERTENCIA SOBRE LOS TELÉFONOS DE SINIESTROS                        │
 * │                                                                       │
 * │ VERIFICAR CADA NÚMERO contra la póliza vigente o el sitio oficial de  │
 * │ la aseguradora ANTES de usarlos en producción. Este es el número que  │
 * │ el conductor marca desde el lugar de un accidente: un dígito mal      │
 * │ puesto deja a alguien tirado en la carretera.                         │
 * │                                                                       │
 * │ Los que aquí aparecen como null NO son un olvido: son las compañías   │
 * │ cuyo número de siniestros no se pudo confirmar. Se dejan vacíos a     │
 * │ propósito en lugar de poner un número aproximado, porque un campo     │
 * │ vacío se nota y se corrige, mientras que uno incorrecto se marca.     │
 * │                                                                       │
 * │ Además, varias aseguradoras manejan un 800 distinto por producto      │
 * │ (flotillas vs. particular). Al contratar, pedir el de flotilla.       │
 * └──────────────────────────────────────────────────────────────────────┘
 *
 * rfc, contacto_nombre y contacto_email se dejan en null a propósito: los
 * llena el usuario cuando establece la relación comercial con cada compañía
 * y le asignan un ejecutivo de cuenta.
 *
 * Mantenimiento: el padrón de aseguradoras es estable, pero hay fusiones y
 * cambios de marca de vez en cuando. Revisar cada año.
 */
class AseguradorasSeeder extends Seeder
{
    public function run(): void
    {
        // [nombre, telefono_siniestros]
        $aseguradoras = [
            // ---------- Teléfono de siniestros precargado (VERIFICAR) ----------
            ['GNP Seguros',         '800 400 9000'],
            ['Qualitas',            '800 288 6700'],
            ['AXA',                 '800 900 1292'],
            ['Mapfre',              '800 062 7373'],
            ['HDI Seguros',         '800 019 6000'],
            ['ANA Seguros',         '800 911 2627'],
            ['Inbursa',             '800 909 0000'],

            // ---------- Sin número confirmado: lo captura el usuario ----------
            ['Chubb',               null],
            ['Zurich',              null],
            ['Banorte',             null],
            ['BBVA Seguros',        null],
            ['Atlas',               null],
            ['El Águila',           null],
            ['Afirme',              null],
            ['Primero Seguros',     null],
            ['General de Seguros',  null],
        ];

        $creados = 0;
        $existentes = 0;

        // Una transacción para las 16: agrupa los writes sin sacrificar la
        // idempotencia, porque cada fila pasa por firstOrCreate y la unicidad
        // la garantiza el índice de `nombre`.
        DB::transaction(function () use ($aseguradoras, &$creados, &$existentes) {
            foreach ($aseguradoras as [$nombre, $telefono]) {
                $aseguradora = Aseguradora::firstOrCreate(
                    ['nombre' => $nombre],   // búsqueda: respeta el único de `nombre`
                    [
                        'telefono_siniestros' => $telefono,
                        // Datos de la relación comercial: los llena el usuario.
                        'rfc'                 => null,
                        'contacto_nombre'     => null,
                        'contacto_email'      => null,
                        'activo'              => true,
                    ],
                );

                $aseguradora->wasRecentlyCreated ? $creados++ : $existentes++;
            }
        });

        $sinTelefono = collect($aseguradoras)->whereStrict(1, null)->count();

        $this->command->info("Aseguradoras: {$creados} creadas, {$existentes} ya existían.");
        $this->command->warn("  {$sinTelefono} sin teléfono de siniestros: capturarlo antes de operar.");
        $this->command->warn('  Verificar TODOS los teléfonos contra la póliza vigente.');
    }
}
