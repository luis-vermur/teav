<?php

namespace App\Console\Commands;

use App\Models\Documento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deja la base lista para arrancar en limpio, conservando lo que sí sirve.
 *
 * Borra la operación —clientes, vehículos, contratos, pagos, expedientes— y
 * conserva los catálogos (marcas, modelos, colores, aseguradoras, tipos de
 * documento), los usuarios, los roles y los permisos. Es decir: tira los
 * datos de prueba sin tirar la configuración que costó armar.
 *
 * Se usa dos veces en la vida de un sistema: al pasar de las pruebas a los
 * datos reales, y al montar producción por primera vez.
 *
 *   php artisan teav:limpiar --revisar    ← enseña qué borraría
 *   php artisan teav:limpiar
 */
class LimpiarDatosOperativos extends Command
{
    protected $signature = 'teav:limpiar
        {--revisar : Sólo enseña el conteo de lo que borraría}
        {--forzar : No pregunta; para correr sin consola interactiva}';

    protected $description = 'Borra la operación (clientes, flota, contratos) y conserva catálogos y usuarios';

    /**
     * Orden de borrado: de la hoja a la raíz.
     *
     * No es alfabético ni caprichoso: cada tabla depende de las que están
     * debajo. Invertir el orden hace que PostgreSQL rechace el borrado por
     * llave foránea, que es justo la red de seguridad que queremos.
     */
    protected const TABLAS = [
        'aplicaciones_pago',
        'movimientos_deposito',
        'penalizaciones',
        'pagos_programados',
        'pagos',
        'cierres_contrato',
        'analisis_rentabilidad',
        'contratos',
        'cotizaciones',
        'siniestros',
        'mantenimientos',
        'polizas_seguro',
        'unidad_historial_estatus',
        'documentos',
        'direcciones',
        'referencias',
        'contactos',
        'unidades',
        'arrendatarios',
        'audits',
    ];

    /** Lo que NO se toca, y por qué conviene decirlo en voz alta. */
    protected const SE_CONSERVA = [
        'marcas', 'submarcas', 'colores', 'aseguradoras', 'proveedores',
        'tipos_documento', 'tipos_mantenimiento',
        'users', 'roles', 'permissions',
    ];

    public function handle(): int
    {
        $conteos = [];

        foreach (self::TABLAS as $tabla) {
            $n = DB::table($tabla)->count();

            if ($n > 0) {
                $conteos[$tabla] = $n;
            }
        }

        if (! $conteos) {
            $this->components->info('No hay nada que borrar: la operación ya está vacía.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=red;options=bold>SE BORRA</>', '<fg=red;options=bold>registros</>');

        foreach ($conteos as $tabla => $n) {
            $this->components->twoColumnDetail($tabla, (string) $n);
        }

        $this->newLine();
        $this->line('  <fg=green>Se conserva:</> ' . implode(', ', self::SE_CONSERVA));

        $archivos = Documento::count();

        if ($archivos > 0) {
            $this->line("  <fg=yellow>Además se borrarán {$archivos} archivos del disco privado.</>");
        }

        $this->newLine();

        if ($this->option('revisar')) {
            $this->components->info('Modo revisión: no se borró nada.');

            return self::SUCCESS;
        }

        if (! $this->option('forzar')
            && ! $this->confirm('Esto no se puede deshacer. ¿Hay un respaldo reciente y quieres continuar?', false)) {
            $this->components->warn('Cancelado.');

            return self::SUCCESS;
        }

        // Los archivos primero: si se borran las filas antes, ya no se sabe
        // qué archivos quedaron huérfanos en el disco.
        $this->borrarArchivos();

        DB::transaction(function () use ($conteos) {
            foreach (array_keys($conteos) as $tabla) {
                DB::table($tabla)->delete();
            }

            // Las secuencias se reinician para que los folios vuelvan a
            // empezar en 1 y no en el número que dejó la prueba.
            foreach (array_keys($conteos) as $tabla) {
                DB::statement("ALTER SEQUENCE IF EXISTS {$tabla}_id_seq RESTART WITH 1");
            }
        });

        $this->components->info('Listo. Catálogos, usuarios y permisos intactos.');

        return self::SUCCESS;
    }

    /** Quita del disco privado lo que estaba registrado como documento. */
    protected function borrarArchivos(): void
    {
        $borrados = 0;

        foreach (Documento::withTrashed()->get() as $documento) {
            $disco = Storage::disk($documento->disco);

            if ($disco->exists($documento->ruta)) {
                $disco->delete($documento->ruta);
                $borrados++;
            }
        }

        if ($borrados > 0) {
            $this->line("  {$borrados} archivos borrados del disco privado.");
        }
    }
}
