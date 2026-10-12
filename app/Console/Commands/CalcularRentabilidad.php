<?php

namespace App\Console\Commands;

use App\Models\Unidad;
use App\Services\RentabilidadService;
use Illuminate\Console\Command;

/**
 * Recalcula la rentabilidad real de la flota.
 *
 * El servicio existía desde hace tiempo pero NADIE lo llamaba: ni un
 * comando, ni una acción del panel, ni un observer. El widget de
 * rentabilidad leía una tabla que nunca se llenaba, así que en la práctica
 * mostraba "Sin datos" para siempre.
 */
class CalcularRentabilidad extends Command
{
    protected $signature = 'teav:rentabilidad
                            {--unidad= : Sólo esta unidad, por código interno}
                            {--todas : Incluye vendidas y dadas de baja}';

    protected $description = 'Recalcula la rentabilidad real de las unidades';

    public function handle(RentabilidadService $servicio): int
    {
        $consulta = Unidad::query();

        if ($codigo = $this->option('unidad')) {
            $consulta->where('codigo_interno', $codigo);
        } elseif (! $this->option('todas')) {
            // Por defecto sólo la flota viva: recalcular una unidad vendida
            // hace tres años no aporta nada y ensucia el histórico.
            $consulta->activas();
        }

        $unidades = $consulta->get();

        if ($unidades->isEmpty()) {
            $this->warn('No hay unidades que calcular.');

            return self::SUCCESS;
        }

        $ok = 0;
        $fallos = 0;

        foreach ($unidades as $unidad) {
            try {
                $analisis = $servicio->calcularReal($unidad);
                $ok++;

                $this->line(sprintf(
                    '  %-12s rendimiento anual %s%%%s',
                    $unidad->codigo_interno,
                    number_format((float) $analisis->rendimiento_anual * 100, 2),
                    $analisis->brecha_rendimiento !== null
                        ? sprintf(' · brecha %s pts', number_format((float) $analisis->brecha_rendimiento * 100, 1))
                        : '',
                ));
            } catch (\Throwable $e) {
                $fallos++;
                // Una unidad con datos incompletos no debe tumbar el lote.
                $this->error("  {$unidad->codigo_interno}: {$e->getMessage()}");
            }
        }

        $this->info("Rentabilidad calculada: {$ok} unidades" . ($fallos ? " · {$fallos} con error" : ''));

        return $fallos > 0 ? self::FAILURE : self::SUCCESS;
    }
}
