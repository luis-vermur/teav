<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Resources\Mantenimientos\MantenimientoResource;
use App\Filament\Resources\PolizaSeguros\PolizaSeguroResource;
use App\Models\Contrato;
use App\Models\Documento;
use App\Models\Mantenimiento;
use App\Models\PolizaSeguro;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class Alertas extends StatsOverviewWidget
{
    /**
     * Nota sobre las URLs: en Filament 5 la clave del query string para los
     * filtros de tabla es `filters`, no `tableFilters` (eso era Filament 3).
     * Lo declara ListRecords con #[Url(as: 'filters')]. Con `tableFilters`
     * la página abre sin filtrar y en silencio.
     */
    protected static ?int $sort = 2;

    /**
     * Vencimientos de pólizas, documentos y servicios: es
 * información operativa de la flota, no cifras del negocio.
     */
    public static function canView(): bool
    {
        return auth()->user()?->can('ver_unidad') ?? false;
    }

    protected function getStats(): array
    {
        $polizas = PolizaSeguro::porVencer(30)->count();

        $docsVencidos = Documento::vencidos()->count();
        $docsPorVencer = Documento::porVencer(30)->count();

        $servicios = Mantenimiento::proximos(30)->count();
        $serviciosVencidos = Mantenimiento::vencidos()->count();

        $contratos = Contrato::porVencer(60)->count();

        return [
            Stat::make('Pólizas por vencer', $polizas)
                ->description('En los próximos 30 días')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($polizas > 0 ? 'warning' : 'success')
                ->url(PolizaSeguroResource::getUrl('index'))
                ->extraAttributes(['class' => 'cursor-pointer']),

            // Sin url a propósito: los documentos son polimórficos (cuelgan de
            // unidades, clientes y contratos), así que no hay una sola lista
            // a la que llevar.
            Stat::make('Documentos', $docsVencidos + $docsPorVencer)
                ->description($docsVencidos > 0
                    ? "{$docsVencidos} vencidos · {$docsPorVencer} por vencer"
                    : "{$docsPorVencer} por vencer")
                ->descriptionIcon('heroicon-m-document-text')
                ->color($docsVencidos > 0 ? 'danger' : ($docsPorVencer > 0 ? 'warning' : 'success')),

            Stat::make('Servicios', $servicios + $serviciosVencidos)
                ->description($serviciosVencidos > 0
                    ? "{$serviciosVencidos} atrasados · {$servicios} próximos"
                    : "{$servicios} próximos")
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color($serviciosVencidos > 0 ? 'danger' : 'info')
                ->url(MantenimientoResource::getUrl('index'))
                ->extraAttributes(['class' => 'cursor-pointer']),

            Stat::make('Contratos por vencer', $contratos)
                ->description('En los próximos 60 días')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($contratos > 0 ? 'warning' : 'gray')
                ->url(ContratoResource::getUrl('index', [
                    'filters' => ['por_vencer_pronto' => ['isActive' => true]],
                ]))
                ->extraAttributes(['class' => 'cursor-pointer']),
        ];
    }
}
