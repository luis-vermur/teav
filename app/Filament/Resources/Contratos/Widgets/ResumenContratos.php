<?php

namespace App\Filament\Resources\Contratos\Widgets;

use App\Models\Contrato;
use App\Models\PagoProgramado;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Se muestra arriba de la lista de contratos, no en el tablero general.
 * Da contexto del módulo antes de mirar las filas.
 */
class ResumenContratos extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $vigentes  = Contrato::vigentes()->count();
        $borradores = Contrato::where('estatus', 'borrador')->count();
        $porVencer = Contrato::porVencer(60)->count();

        $flujo = (float) Contrato::vigentes()->sum('renta_con_iva');

        $vencido = (float) PagoProgramado::query()
            ->whereIn('estatus', ['pendiente', 'parcial', 'vencido'])
            ->where('fecha_limite', '<', now())
            ->sum('saldo');

        $conAdeudo = Contrato::vigentes()->conAdeudo()->count();

        return [
            Stat::make('Contratos vigentes', $vigentes)
                ->description($borradores > 0 ? "{$borradores} en borrador" : 'Ninguno pendiente de activar')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('success'),

            Stat::make('Flujo mensual', '$' . number_format($flujo, 0))
                ->description('Suma de rentas con IVA')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info'),

            Stat::make('Cartera vencida', '$' . number_format($vencido, 0))
                ->description($conAdeudo > 0 ? "{$conAdeudo} contrato(s) con adeudo" : 'Todo al corriente')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($vencido > 0 ? 'danger' : 'success'),

            Stat::make('Por vencer', $porVencer)
                ->description('Terminan en los próximos 60 días')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($porVencer > 0 ? 'warning' : 'gray'),
        ];
    }
}
