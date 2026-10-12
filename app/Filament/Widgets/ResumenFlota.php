<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Resources\Unidads\UnidadResource;
use App\Models\Contrato;
use App\Models\PagoProgramado;
use App\Models\Unidad;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumenFlota extends StatsOverviewWidget
{
    /**
     * Nota sobre las URLs: en Filament 5 la clave del query string para los
     * filtros de tabla es `filters`, no `tableFilters` (eso era Filament 3).
     * Lo declara ListRecords con #[Url(as: 'filters')]. Con `tableFilters`
     * la página abre sin filtrar y en silencio.
     */
    protected static ?int $sort = 1;

    /**
     * Muestra flujo mensual, inversión de la flota y cartera vencida:
 * son las cifras del negocio, no información operativa.
     */
    public static function canView(): bool
    {
        return auth()->user()?->can('ver_rentabilidad') ?? false;
    }

    protected function getStats(): array
    {
        $activas     = Unidad::activas()->count();
        $arrendadas  = Unidad::arrendadas()->count();
        $disponibles = Unidad::disponibles()->count();

        $ocupacion = $activas > 0 ? round($arrendadas / $activas * 100) : 0;

        $inversion = (float) Unidad::activas()->sum('precio_adquisicion')
                   + (float) Unidad::activas()->sum('gastos_adquisicion');

        $rentaMensual = (float) Contrato::vigentes()->sum('renta_con_iva');

        $vencido = (float) PagoProgramado::query()
            ->whereIn('estatus', ['pendiente', 'parcial', 'vencido'])
            ->where('fecha_limite', '<', now())
            ->sum('saldo');

        $contratosConAdeudo = Contrato::vigentes()->conAdeudo()->count();

        return [
            Stat::make('Ocupación de flota', "{$ocupacion}%")
                ->description("{$arrendadas} arrendadas · {$disponibles} disponibles")
                ->descriptionIcon('heroicon-m-truck')
                ->color($ocupacion >= 80 ? 'success' : ($ocupacion >= 60 ? 'warning' : 'danger'))
                // Las unidades libres son sobre las que hay que actuar.
                ->url(UnidadResource::getUrl('index', [
                    'filters' => ['estatus' => ['values' => ['disponible']]],
                ]))
                ->extraAttributes(['class' => 'cursor-pointer']),

            Stat::make('Flujo mensual', '$' . number_format($rentaMensual, 0))
                ->description('Renta de contratos vigentes, con IVA')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->url(ContratoResource::getUrl('index', [
                    'filters' => ['estatus' => ['values' => ['vigente']]],
                ]))
                ->extraAttributes(['class' => 'cursor-pointer']),

            Stat::make('Inversión en flota', '$' . number_format($inversion, 0))
                ->description("{$activas} unidades activas")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info')
                // Toda la flota, sin filtro.
                ->url(UnidadResource::getUrl('index'))
                ->extraAttributes(['class' => 'cursor-pointer']),

            Stat::make('Cartera vencida', '$' . number_format($vencido, 0))
                ->description($contratosConAdeudo > 0
                    ? "{$contratosConAdeudo} contrato(s) con adeudo"
                    : 'Sin adeudos')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($vencido > 0 ? 'danger' : 'success')
                ->url(ContratoResource::getUrl('index', [
                    'filters' => ['con_adeudo' => ['isActive' => true]],
                ]))
                ->extraAttributes(['class' => 'cursor-pointer']),
        ];
    }
}