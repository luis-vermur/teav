<?php

namespace App\Console\Commands;

use App\Models\Arrendatario;
use App\Models\Aseguradora;
use App\Models\Color;
use App\Models\Documento;
use App\Models\Marca;
use App\Models\PolizaSeguro;
use App\Models\Submarca;
use App\Models\TipoDocumento;
use App\Models\Unidad;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Carga la flota real desde archivos que viven FUERA del proyecto.
 *
 * Los datos de clientes y vehículos no van en un seeder: un seeder es código
 * que se versiona y se comparte, y aquí hay RFC, domicilios y números de
 * póliza de gente real. Por eso este comando lee de una carpeta externa que
 * nunca entra al repositorio.
 *
 * Es idempotente: se puede correr las veces que haga falta. Las llaves
 * naturales son el RFC del cliente, el VIN del vehículo y la pareja
 * aseguradora + número de póliza. Volver a correrlo actualiza, no duplica.
 *
 *   php artisan teav:importar --revisar     ← no escribe nada, sólo reporta
 *   php artisan teav:importar
 *   php artisan teav:importar --documentos
 */
class ImportarFlota extends Command
{
    protected $signature = 'teav:importar
        {--ruta= : Carpeta con clientes.csv y flota.csv}
        {--archivos= : Carpeta con las subcarpetas de cada auto (TC y pólizas)}
        {--documentos : Además de los datos, sube las TC y carátulas al expediente}
        {--revisar : No escribe nada; sólo valida y reporta qué haría}';

    protected $description = 'Importa clientes, vehículos y pólizas reales desde archivos externos';

    /** Carpeta por omisión, hermana del proyecto y fuera de control de versiones. */
    protected const RUTA_POR_OMISION = '../datos-reales';

    protected bool $revisar = false;

    /** Lo que se va a reportar al final. */
    protected array $resumen = [];

    public function handle(): int
    {
        $this->revisar = (bool) $this->option('revisar');

        $ruta = rtrim($this->option('ruta') ?: base_path(self::RUTA_POR_OMISION), '/');

        $clientesCsv = "{$ruta}/clientes.csv";
        $flotaCsv    = "{$ruta}/flota.csv";

        foreach ([$clientesCsv, $flotaCsv] as $archivo) {
            if (! is_readable($archivo)) {
                $this->error("No encuentro {$archivo}");

                return self::FAILURE;
            }
        }

        $clientes = $this->leerCsv($clientesCsv);
        $flota    = $this->leerCsv($flotaCsv);

        // Antes de tocar la base: ¿los datos alcanzan?
        if (! $this->validar($clientes, $flota)) {
            return self::FAILURE;
        }

        if ($this->revisar) {
            $this->warn('Modo revisión: no se escribió nada.');
        }

        $usuario = User::orderBy('id')->first();

        if (! $usuario) {
            $this->error('No hay ningún usuario en la base; los documentos necesitan un responsable.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($clientes, $flota, $usuario) {
                $this->components->info('Catálogos');
                $this->catalogos($flota);

                $this->components->info('Clientes');
                $mapaClientes = $this->clientes($clientes);

                $this->components->info('Vehículos y pólizas');
                $this->vehiculos($flota, $mapaClientes);

                if ($this->option('documentos')) {
                    $this->components->info('Expediente');
                    $this->documentos($flota, $usuario);
                }

                if ($this->revisar) {
                    // Deshace todo: la revisión mira, no toca.
                    throw new SoloRevisando;
                }
            });
        } catch (SoloRevisando) {
            // Previsto: la transacción ya quedó revertida.
        }

        $this->newLine();
        $this->components->info($this->revisar ? 'Revisión terminada' : 'Importación terminada');

        foreach ($this->resumen as $linea) {
            $this->line("  {$linea}");
        }

        return self::SUCCESS;
    }

    // ───────────────────────────── validación ─────────────────────────────

    /**
     * Lo que no se puede inventar.
     *
     * `precio_adquisicion` es obligatorio en la base Y es el denominador de
     * todos los rendimientos. Un vehículo cargado sin precio no da un error
     * visible: da rendimientos absurdos que nadie relaciona con una carga
     * incompleta meses después. Mejor negarse aquí.
     */
    protected function validar(array $clientes, array $flota): bool
    {
        $problemas = [];

        foreach ($flota as $i => $f) {
            $donde = "flota.csv fila " . ($i + 2) . " ({$f['codigo_interno']})";

            if (blank($f['precio_adquisicion'] ?? null)) {
                $problemas[] = "{$donde}: falta precio_adquisicion";
            }

            if (strlen($f['vin'] ?? '') !== 17) {
                $problemas[] = "{$donde}: el VIN no tiene 17 caracteres";
            }

            if (! Marca::whereRaw('upper(nombre) = ?', [strtoupper($f['marca'])])->exists()) {
                $problemas[] = "{$donde}: la marca {$f['marca']} no está en el catálogo";
            }

            $clave = $f['cliente'] ?? '';

            if (filled($clave) && ! collect($clientes)->firstWhere('clave', $clave)) {
                $problemas[] = "{$donde}: el cliente «{$clave}» no está en clientes.csv";
            }
        }

        foreach ($clientes as $i => $c) {
            $donde = "clientes.csv fila " . ($i + 2) . " ({$c['clave']})";

            if (blank($c['rfc'] ?? null)) {
                $problemas[] = "{$donde}: falta el RFC";
            }

            if ($c['tipo_persona'] === 'moral' && blank($c['razon_social'] ?? null)) {
                $problemas[] = "{$donde}: una persona moral necesita razón social";
            }

            if ($c['tipo_persona'] === 'fisica' && blank($c['nombre'] ?? null)) {
                $problemas[] = "{$donde}: una persona física necesita nombre";
            }
        }

        // El código interno es único: avisar antes, no reventar a media carga.
        foreach ($flota as $f) {
            $choca = Unidad::withTrashed()
                ->where('codigo_interno', $f['codigo_interno'])
                ->where('vin', '!=', $f['vin'])
                ->first();

            if ($choca) {
                $problemas[] = "El código {$f['codigo_interno']} ya lo usa otro vehículo "
                    . "(VIN {$choca->vin}). Cámbialo en el CSV o borra el registro viejo.";
            }
        }

        if ($problemas) {
            $this->error('No puedo importar todavía:');
            $this->newLine();

            foreach ($problemas as $p) {
                $this->line("  <fg=red>•</> {$p}");
            }

            $this->newLine();

            return false;
        }

        $this->components->info('Los datos están completos.');

        return true;
    }

    // ───────────────────────────── catálogos ─────────────────────────────

    /** Crea los modelos y colores que la flota necesita y aún no existen. */
    protected function catalogos(array $flota): void
    {
        $modelos = 0;
        $colores = 0;

        foreach ($flota as $f) {
            $marca = Marca::whereRaw('upper(nombre) = ?', [strtoupper($f['marca'])])->first();

            $existe = Submarca::where('marca_id', $marca->id)
                ->whereRaw('upper(nombre) = ?', [strtoupper($f['modelo'])])
                ->exists();

            if (! $existe) {
                Submarca::create([
                    'marca_id' => $marca->id,
                    'nombre'   => $f['modelo'],
                    'activo'   => true,
                ]);
                $modelos++;
                $this->line("    + modelo {$f['marca']} {$f['modelo']}");
            }

            if (blank($f['color'] ?? null)) {
                continue;
            }

            if (! Color::whereRaw('upper(nombre) = ?', [strtoupper($f['color'])])->exists()) {
                Color::create(['nombre' => $f['color'], 'hex' => $this->hexDe($f['color'])]);
                $colores++;
                $this->line("    + color {$f['color']}");
            }
        }

        $this->resumen[] = "Catálogos: {$modelos} modelos y {$colores} colores nuevos";
    }

    /**
     * Color aproximado para la muestra de la tabla.
     *
     * Son nombres de fábrica ("Charente Grey", "Fuji White"), no colores web:
     * lo que se busca es que la muestra se parezca, no que sea exacta. Quien
     * quiera precisión lo corrige desde el panel.
     */
    protected function hexDe(string $nombre): string
    {
        $n = mb_strtolower($nombre);

        return match (true) {
            str_contains($n, 'blanco'), str_contains($n, 'white')  => '#F2F2F0',
            str_contains($n, 'negro'),  str_contains($n, 'black')  => '#111113',
            str_contains($n, 'plata'),  str_contains($n, 'silver') => '#C8CACB',
            str_contains($n, 'rojo'),   str_contains($n, 'red')    => '#A61C23',
            str_contains($n, 'azul'),   str_contains($n, 'blue')   => '#1E3A5F',
            str_contains($n, 'gris'),   str_contains($n, 'grey'),
            str_contains($n, 'gray')                               => '#6B6E72',
            default                                                => '#9AA0A6',
        };
    }

    // ───────────────────────────── clientes ─────────────────────────────

    /** @return array<string,int> clave del CSV → id del arrendatario */
    protected function clientes(array $clientes): array
    {
        $mapa = [];

        foreach ($clientes as $c) {
            $arrendatario = Arrendatario::updateOrCreate(
                ['rfc' => strtoupper(trim($c['rfc']))],
                [
                    'tipo_persona'       => $c['tipo_persona'],
                    'razon_social'       => $this->oNulo($c['razon_social'] ?? null),
                    'nombre_comercial'   => $this->oNulo($c['razon_social'] ?? null),
                    'nombre'             => $this->oNulo($c['nombre'] ?? null),
                    'apellido_paterno'   => $this->oNulo($c['apellido_paterno'] ?? null),
                    'apellido_materno'   => $this->oNulo($c['apellido_materno'] ?? null),
                    'curp'               => $this->oNulo($c['curp'] ?? null),
                    'regimen_fiscal'     => $this->oNulo($c['regimen_fiscal'] ?? null),
                    'fecha_constitucion' => $this->oNulo($c['fecha_constitucion'] ?? null),
                    'ocupacion'          => $this->oNulo($c['ocupacion'] ?? null),
                    'email'              => $this->oNulo($c['email'] ?? null),
                    'telefono'           => $this->oNulo($c['telefono'] ?? null),
                    /**
                     * Entran como `activo` porque son los clientes con los que
                     * ya se opera. Un prospecto no podría firmar contrato, y
                     * la activación del contrato valida justo eso.
                     */
                    'estatus'            => 'activo',
                ]
            );

            if (filled($c['calle'] ?? null)) {
                $arrendatario->direcciones()->updateOrCreate(
                    ['tipo' => 'fiscal'],
                    [
                        'calle'           => $c['calle'],
                        'numero_exterior' => $this->oNulo($c['numero_exterior'] ?? null),
                        'numero_interior' => $this->oNulo($c['numero_interior'] ?? null),
                        'colonia'         => $this->oNulo($c['colonia'] ?? null),
                        'municipio'       => $c['municipio'],
                        'estado'          => $c['estado'],
                        'codigo_postal'   => $c['codigo_postal'],
                        'es_principal'    => true,
                    ]
                );
            }

            $mapa[$c['clave']] = $arrendatario->id;

            $this->line("    {$arrendatario->codigo}  {$arrendatario->nombre_completo}");
        }

        $this->resumen[] = 'Clientes: ' . count($mapa);

        return $mapa;
    }

    // ───────────────────────────── vehículos ─────────────────────────────

    protected function vehiculos(array $flota, array $mapaClientes): void
    {
        $chubb = Aseguradora::where('nombre', 'ilike', '%chubb%')->first();
        $polizas = 0;

        foreach ($flota as $f) {
            $marca = Marca::whereRaw('upper(nombre) = ?', [strtoupper($f['marca'])])->first();

            $submarca = Submarca::where('marca_id', $marca->id)
                ->whereRaw('upper(nombre) = ?', [strtoupper($f['modelo'])])
                ->first();

            $color = filled($f['color'] ?? null)
                ? Color::whereRaw('upper(nombre) = ?', [strtoupper($f['color'])])->first()
                : null;

            // El VIN es la identidad del auto: es lo único que no cambia nunca.
            $unidad = Unidad::updateOrCreate(
                ['vin' => strtoupper(trim($f['vin']))],
                [
                    'codigo_interno'          => $f['codigo_interno'],
                    'submarca_id'             => $submarca->id,
                    'color_id'                => $color?->id,
                    'anio'                    => (int) $f['anio'],
                    'version'                 => $this->oNulo($f['version'] ?? null),
                    'placas'                  => $this->oNulo($f['placas'] ?? null),
                    'numero_motor'            => $this->oNulo($f['numero_motor'] ?? null),
                    'transmision'             => $this->oNulo($f['transmision'] ?? null),
                    'combustible'             => $this->oNulo($f['combustible'] ?? null),
                    'numero_pasajeros'        => $this->oNuloEntero($f['numero_pasajeros'] ?? null),
                    'precio_adquisicion'      => (float) $f['precio_adquisicion'],
                    'precio_lista'            => $this->oNuloDecimal($f['precio_lista'] ?? null),
                    'fecha_adquisicion'       => $this->oNulo($f['fecha_adquisicion'] ?? null),
                    'valor_residual_estimado' => $this->oNuloDecimal($f['valor_residual_estimado'] ?? null),
                    'kilometraje_actual'      => (int) ($f['kilometraje_actual'] ?? 0),
                    /**
                     * De dónde salió el precio. Importa: mientras sea una
                     * estimación de mercado y no la factura, todo rendimiento
                     * calculado sobre este auto es aproximado, y quien abra
                     * la ficha dentro de seis meses tiene que poder saberlo.
                     */
                    'notas'                   => $this->oNulo($f['notas'] ?? null),
                    /**
                     * Entran como `disponible`, no como `arrendada`: el estatus
                     * de arrendada lo pone la activación del contrato, que es
                     * la que además genera el calendario de pagos. Marcarlo a
                     * mano dejaría un auto "rentado" sin contrato detrás.
                     */
                    'estatus'                 => 'disponible',
                ]
            );

            $this->line("    {$unidad->codigo_interno}  {$unidad->descripcion}");

            if (blank($f['poliza_numero'] ?? null) || ! $chubb) {
                continue;
            }

            PolizaSeguro::updateOrCreate(
                [
                    'aseguradora_id' => $chubb->id,
                    'numero_poliza'  => $f['poliza_numero'],
                ],
                [
                    'unidad_id'        => $unidad->id,
                    'tipo_cobertura'   => $f['poliza_cobertura'] ?: 'amplia',
                    'vigencia_inicio'  => $f['poliza_vigencia_inicio'],
                    'vigencia_fin'     => $f['poliza_vigencia_fin'],
                    'prima_total'      => (float) $f['poliza_prima_total'],
                    'suma_asegurada'   => $this->oNuloDecimal($f['poliza_suma_asegurada'] ?? null),
                    'deducible_danos'  => $this->oNuloDecimal($f['poliza_deducible_danos'] ?? null),
                    'deducible_robo'   => $this->oNuloDecimal($f['poliza_deducible_robo'] ?? null),
                    'forma_pago'       => $f['poliza_forma_pago'] ?: 'contado',
                    'estatus'          => 'vigente',
                ]
            );

            $polizas++;
        }

        $this->resumen[] = 'Vehículos: ' . count($flota) . " · Pólizas: {$polizas}";
    }

    // ───────────────────────────── documentos ─────────────────────────────

    /**
     * Copia las TC y las carátulas al disco privado y las registra.
     *
     * La TC cuelga del vehículo y la carátula de su póliza, que es donde las
     * busca quien las necesita. El hash sha256 sirve para no volver a subir
     * el mismo archivo si el comando se corre dos veces.
     */
    protected function documentos(array $flota, User $usuario): void
    {
        $base = rtrim($this->option('archivos') ?: '', '/');

        if (! $base || ! is_dir($base)) {
            $this->warn('    Sin --archivos no hay de dónde copiar; me salto el expediente.');

            return;
        }

        $subidos = 0;
        $disco   = Storage::disk('documentos');

        foreach ($flota as $f) {
            $carpeta = "{$base}/{$f['carpeta']}";

            if (! is_dir($carpeta)) {
                $this->warn("    No encuentro la carpeta {$f['carpeta']}");

                continue;
            }

            $unidad = Unidad::where('vin', strtoupper(trim($f['vin'])))->first();
            $poliza = PolizaSeguro::where('numero_poliza', $f['poliza_numero'])->first();

            foreach (glob("{$carpeta}/*") as $archivo) {
                if (is_dir($archivo) || str_starts_with(basename($archivo), '.')) {
                    continue;
                }

                [$dueno, $claveTipo] = $this->aQuienPertenece($archivo, $unidad, $poliza);

                if (! $dueno) {
                    $this->warn('    No sé a qué corresponde ' . basename($archivo));

                    continue;
                }

                $tipo = TipoDocumento::where('clave', $claveTipo)->first();

                if (! $tipo) {
                    continue;
                }

                /**
                 * Las TC vienen del iPhone, en HEIC. Ni el navegador las
                 * abre ni el tipo de documento las acepta (sólo pdf, jpeg y
                 * png), así que un HEIC subido tal cual es un documento que
                 * aparece en la lista y nadie puede ver. Se convierte antes.
                 */
                $original = $archivo;
                $archivo  = $this->aFormatoVisible($archivo);

                if (! $archivo) {
                    continue;
                }

                $hash = hash_file('sha256', $archivo);

                // Ya está cargado: no duplicar en una segunda corrida.
                if (Documento::where('hash', $hash)->exists()) {
                    continue;
                }

                $carpetaDestino = $dueno instanceof Unidad ? 'unidades' : 'polizas';

                /**
                 * Si hubo conversión, el archivo vive en un temporal con
                 * nombre de hash. Lo que se guarda y se enseña es el nombre
                 * que le puso el usuario, con la extensión ya convertida.
                 */
                $nombreVisible = pathinfo($original, PATHINFO_FILENAME)
                    . '.' . pathinfo($archivo, PATHINFO_EXTENSION);

                $destino = "{$carpetaDestino}/{$dueno->id}/{$nombreVisible}";

                if (! $this->revisar) {
                    $disco->put($destino, file_get_contents($archivo), 'private');
                }

                Documento::create([
                    'documentable_type'  => $dueno::class,
                    'documentable_id'    => $dueno->id,
                    'tipo_documento_id'  => $tipo->id,
                    'nombre_original'    => $nombreVisible,
                    'ruta'               => $destino,
                    'disco'              => 'documentos',
                    'mime'               => mime_content_type($archivo),
                    'tamano'             => filesize($archivo),
                    'hash'               => $hash,
                    // La TC y la carátula vencen; es justo lo que vigila el panel.
                    'fecha_vencimiento'  => $claveTipo === 'caratula_poliza'
                        ? ($f['poliza_vigencia_fin'] ?: null)
                        : null,
                    'vigente'            => true,
                    'subido_por'         => $usuario->id,
                ]);

                $subidos++;
                $this->line("    + {$nombreVisible}  →  {$tipo->nombre}");
            }
        }

        $this->resumen[] = "Documentos: {$subidos} archivos al expediente";
    }

    /**
     * Devuelve una ruta que el panel sí pueda servir.
     *
     * Un HEIC se convierte a JPEG en un archivo temporal; el original del
     * usuario no se toca. En macOS lo hace `sips`, que viene con el sistema;
     * si no hay con qué convertir, se avisa y ese documento se omite en vez
     * de guardar algo que después nadie podrá abrir.
     */
    protected function aFormatoVisible(string $archivo): ?string
    {
        $extension = mb_strtolower(pathinfo($archivo, PATHINFO_EXTENSION));

        if (! in_array($extension, ['heic', 'heif'], true)) {
            return $archivo;
        }

        $destino = sys_get_temp_dir() . '/teav-' . md5($archivo) . '.jpg';

        if (is_file($destino)) {
            return $destino;
        }

        foreach ([
            ['sips', '-s', 'format', 'jpeg', $archivo, '--out', $destino],
            ['magick', $archivo, $destino],
            ['convert', $archivo, $destino],
        ] as $orden) {
            if (! $this->hayComando($orden[0])) {
                continue;
            }

            $proceso = proc_open(
                $orden,
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $tuberias
            );

            if (is_resource($proceso)) {
                foreach ($tuberias as $t) {
                    fclose($t);
                }

                proc_close($proceso);
            }

            if (is_file($destino) && filesize($destino) > 0) {
                return $destino;
            }
        }

        $this->warn('    No pude convertir ' . basename($archivo)
            . ' (HEIC). Conviértela a JPG y vuelve a correr el comando.');

        return null;
    }

    protected function hayComando(string $nombre): bool
    {
        return trim((string) shell_exec('command -v ' . escapeshellarg($nombre) . ' 2>/dev/null')) !== '';
    }

    /** Decide de qué cuelga cada archivo a partir de su nombre. */
    protected function aQuienPertenece(string $archivo, ?Unidad $unidad, ?PolizaSeguro $poliza): array
    {
        $nombre = mb_strtoupper(basename($archivo));

        return match (true) {
            str_starts_with($nombre, 'TC ')      => [$unidad, 'tarjeta_circulacion'],
            str_contains($nombre, 'RECIBO')      => [$unidad, 'tenencia'],
            str_starts_with($nombre, 'M6')       => [$poliza, 'caratula_poliza'],
            default                              => [null, null],
        };
    }

    // ───────────────────────────── utilería ─────────────────────────────

    /** @return array<int,array<string,string>> */
    protected function leerCsv(string $ruta): array
    {
        $manejador = fopen($ruta, 'r');
        $encabezado = fgetcsv($manejador);
        $filas = [];

        while (($fila = fgetcsv($manejador)) !== false) {
            if ($fila === [null] || $fila === []) {
                continue;
            }

            // Rellena las columnas que el CSV trae cortadas al final.
            $fila = array_pad($fila, count($encabezado), '');

            $filas[] = array_combine($encabezado, array_map('trim', $fila));
        }

        fclose($manejador);

        return $filas;
    }

    protected function oNulo(?string $v): ?string
    {
        return filled($v) ? $v : null;
    }

    protected function oNuloEntero(?string $v): ?int
    {
        return filled($v) ? (int) $v : null;
    }

    protected function oNuloDecimal(?string $v): ?float
    {
        return filled($v) ? (float) $v : null;
    }
}

/** Señal interna para revertir la transacción en modo revisión. */
class SoloRevisando extends \RuntimeException {}
