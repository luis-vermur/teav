<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Respaldo de la base de datos con pg_dump.
 *
 * El sistema guarda contratos, pagos y expedientes de clientes: perder la
 * base significa perder el negocio. No había ninguna estrategia de
 * respaldo configurada.
 *
 * Deliberadamente sin dependencias externas: usa pg_dump, que ya viene con
 * PostgreSQL. Los archivos van al disco local, fuera de `public`.
 *
 * IMPORTANTE: un respaldo que vive en el mismo disco que la base no
 * protege contra la falla de ese disco. Hay que copiarlos fuera del
 * servidor (S3, Drive, otro equipo). Esto resuelve el "no hay respaldo",
 * no el "está a salvo".
 */
class RespaldarBaseDeDatos extends Command
{
    protected $signature = 'teav:respaldar
                            {--conservar=14 : Cuántos respaldos diarios conservar}';

    protected $description = 'Genera un respaldo comprimido de la base de datos';

    public function handle(): int
    {
        $conexion = config('database.default');
        $db       = config("database.connections.{$conexion}");

        if ($conexion !== 'pgsql') {
            $this->error("Este comando es para PostgreSQL; la conexión activa es «{$conexion}».");

            return self::FAILURE;
        }

        // Mismo volumen persistente que los expedientes: un respaldo que vive
        // en el disco efímero del contenedor no es un respaldo.
        $carpeta = rtrim(env('TEAV_DISCO_PERSISTENTE', storage_path('app')), '/') . '/respaldos';

        if (! is_dir($carpeta)) {
            mkdir($carpeta, 0755, true);
        }

        $archivo = sprintf('%s/teav-%s.sql.gz', $carpeta, now()->format('Y-m-d_His'));

        // La contraseña va por variable de entorno: ponerla en la línea de
        // comandos la dejaría visible en la lista de procesos.
        $proceso = Process::fromShellCommandline(
            sprintf(
                'pg_dump --host=%s --port=%s --username=%s --dbname=%s --no-owner --no-privileges | gzip > %s',
                escapeshellarg($db['host']),
                escapeshellarg((string) $db['port']),
                escapeshellarg($db['username']),
                escapeshellarg($db['database']),
                escapeshellarg($archivo),
            ),
            env: ['PGPASSWORD' => $db['password'] ?? ''],
            timeout: 600,
        );

        $proceso->run();

        if (! $proceso->isSuccessful()) {
            $this->error('Falló el respaldo: ' . trim($proceso->getErrorOutput()));

            // Un archivo a medias es peor que ninguno: da falsa seguridad.
            if (file_exists($archivo)) {
                unlink($archivo);
            }

            return self::FAILURE;
        }

        $tamano = filesize($archivo);

        // Un dump válido nunca pesa unos pocos bytes.
        if ($tamano < 1024) {
            unlink($archivo);
            $this->error('El respaldo salió vacío; se descartó.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Respaldo creado: %s (%s)',
            basename($archivo),
            number_format($tamano / 1024, 1) . ' KB',
        ));

        $this->limpiarViejos($carpeta);

        return self::SUCCESS;
    }

    /** Conserva sólo los N más recientes; el resto se borra. */
    protected function limpiarViejos(string $carpeta): void
    {
        $conservar = max(1, (int) $this->option('conservar'));

        $archivos = collect(glob("{$carpeta}/teav-*.sql.gz"))
            ->sortByDesc(fn (string $f) => filemtime($f))
            ->values();

        $sobran = $archivos->slice($conservar);

        foreach ($sobran as $f) {
            unlink($f);
        }

        if ($sobran->isNotEmpty()) {
            $this->line("  Se eliminaron {$sobran->count()} respaldos antiguos (se conservan {$conservar}).");
        }
    }
}
