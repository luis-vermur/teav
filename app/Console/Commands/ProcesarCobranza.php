<?php

namespace App\Console\Commands;

use App\Models\Contrato;
use App\Models\PagoProgramado;
use App\Services\ContratoService;
use Illuminate\Console\Command;

class ProcesarCobranza extends Command
{
    protected $signature = 'teav:cobranza {--dry-run : Muestra qué haría sin guardar}';

    protected $description = 'Marca exhibiciones vencidas y genera penalizaciones por mora';

    public function handle(ContratoService $servicio): int
    {
        $seco = $this->option('dry-run');

        // 1) Exhibiciones que pasaron su fecha límite
        $vencidas = PagoProgramado::query()
            ->whereIn('estatus', ['pendiente', 'parcial'])
            ->where('fecha_limite', '<', now())
            ->get();

        foreach ($vencidas as $exhibicion) {
            if (! $seco) {
                $exhibicion->update([
                    'estatus'     => 'vencido',
                    'dias_atraso' => $exhibicion->diasDeAtraso(),
                ]);
            }
        }

        $this->info("Exhibiciones marcadas como vencidas: {$vencidas->count()}");

        // 2) Penalizaciones por mora
        $generadas = 0;
        $contratos = Contrato::vigentes()->with('pagosProgramados')->get();

        foreach ($contratos as $contrato) {
            if (! $seco) {
                $generadas += $servicio->generarPenalizacionesPorMora($contrato);
            }
        }

        $this->info("Penalizaciones generadas o actualizadas: {$generadas}");

        if ($seco) {
            $this->warn('Modo simulación: no se guardó nada.');
        }

        return self::SUCCESS;
    }
}