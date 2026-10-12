<?php

namespace App\Console\Commands;

use App\Models\Contrato;
use App\Models\Cotizacion;
use App\Models\PolizaSeguro;
use Illuminate\Console\Command;

class ActualizarEstatus extends Command
{
    protected $signature = 'teav:estatus';

    protected $description = 'Actualiza estatus de contratos, pólizas y cotizaciones según sus fechas';

    public function handle(): int
    {
        // --- Pólizas ---
        $vencidas = PolizaSeguro::where('estatus', 'vigente')
            ->where('vigencia_fin', '<', now())
            ->update(['estatus' => 'vencida']);

        $porVencer = PolizaSeguro::where('estatus', 'vigente')
            ->whereBetween('vigencia_fin', [now(), now()->addDays(30)])
            ->update(['estatus' => 'por_vencer']);

        $this->info("Pólizas vencidas: {$vencidas} · por vencer: {$porVencer}");

        // --- Cotizaciones ---
        $cotizaciones = Cotizacion::vencidas()->update(['estatus' => 'vencida']);
        $this->info("Cotizaciones expiradas: {$cotizaciones}");

        // --- Contratos ---
        $terminados = 0;
        $porVencerC = 0;
        $incumplen  = 0;

        foreach (Contrato::vigentes()->get() as $contrato) {
            $nuevo = match (true) {
                $contrato->fecha_fin->isPast()    => 'finalizado',
                $contrato->saldoVencido() > 0     => 'en_incumplimiento',
                $contrato->diasParaVencer() <= 60 => 'por_vencer',
                default                           => 'vigente',
            };

            if ($contrato->estatus !== $nuevo) {
                $contrato->update(['estatus' => $nuevo]);

                match ($nuevo) {
                    'finalizado'        => $terminados++,
                    'por_vencer'        => $porVencerC++,
                    'en_incumplimiento' => $incumplen++,
                    default             => null,
                };
            }
        }

        $this->info("Contratos finalizados: {$terminados} · por vencer: {$porVencerC} · en incumplimiento: {$incumplen}");

        return self::SUCCESS;
    }
}