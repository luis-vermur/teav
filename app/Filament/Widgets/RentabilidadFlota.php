<?php

namespace App\Filament\Widgets;

use App\Models\AnalisisRentabilidad;
use App\Models\Unidad;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Rentabilidad de la flota.
 *
 * En este modelo de negocio el cliente absorbe los costos del vehículo, así
 * que el rendimiento ya no se pierde en gastos: se pierde por tener el auto
 * parado, por cartera que nunca se cobró y por vender por debajo de lo
 * estimado. Este tablero mide exactamente eso.
 */
class RentabilidadFlota extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    /** Información sensible del negocio: sólo quien tenga el permiso. */
    public static function canView(): bool
    {
        return auth()->user()?->can('ver_rentabilidad') ?? false;
    }

    protected function getStats(): array
    {
        $analisis = $this->ultimoAnalisisPorUnidad();

        return [
            $this->rendimientoPromedio($analisis),
            $this->ociosidad($analisis),
            $this->brecha($analisis),
            $this->riesgoResidual(),
        ];
    }

    /**
     * El análisis real más reciente de cada unidad activa.
     *
     * Se resuelve en memoria en lugar de con una subconsulta correlacionada:
     * son decenas de unidades, no millones, y así se lee.
     */
    protected function ultimoAnalisisPorUnidad()
    {
        $unidades = Unidad::activas()->pluck('id');

        return AnalisisRentabilidad::query()
            ->where('tipo', 'real')
            ->whereIn('unidad_id', $unidades)
            ->orderByDesc('fecha_calculo')
            ->orderByDesc('id')
            ->get()
            ->unique('unidad_id');
    }

    protected function rendimientoPromedio($analisis): Stat
    {
        if ($analisis->isEmpty()) {
            return Stat::make('Rendimiento anual de la flota', 'Sin datos')
                ->description('Aún no se ha calculado la rentabilidad de ninguna unidad')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('gray');
        }

        $promedio = (float) $analisis->avg('rendimiento_anual');

        return Stat::make('Rendimiento anual de la flota', number_format($promedio * 100, 1) . '%')
            ->description($analisis->count() . ' unidades activas medidas')
            ->descriptionIcon('heroicon-m-arrow-trending-up')
            ->color(match (true) {
                $promedio >= 0.25 => 'success',
                $promedio >= 0.15 => 'warning',
                default           => 'danger',
            });
    }

    protected function ociosidad($analisis): Stat
    {
        if ($analisis->isEmpty()) {
            return Stat::make('Ociosidad de la flota', '—')
                ->description('Sin datos')
                ->color('gray');
        }

        $diasPromedio = (int) round($analisis->avg('dias_ociosos'));
        $dinero       = (float) $analisis->sum('perdida_ociosidad');

        return Stat::make('Ociosidad de la flota', $diasPromedio . ' días')
            ->description($dinero > 0
                ? 'Promedio por unidad · $' . number_format($dinero, 0) . ' de renta no generada'
                : 'Promedio por unidad · sin pérdida registrada')
            ->descriptionIcon('heroicon-m-clock')
            ->color(match (true) {
                $diasPromedio >= 60 => 'danger',
                $diasPromedio >= 30 => 'warning',
                default             => 'success',
            });
    }

    /**
     * Cuántos puntos porcentuales separan la realidad de lo proyectado, y
     * —lo importante— por qué. Se señala el factor que más pesa.
     */
    protected function brecha($analisis): Stat
    {
        $conProyeccion = $analisis->whereNotNull('brecha_rendimiento');

        if ($conProyeccion->isEmpty()) {
            return Stat::make('Brecha proyectado vs. real', 'Sin comparar')
                ->description('Ninguna unidad tiene proyección previa contra la cual medirse')
                ->descriptionIcon('heroicon-m-scale')
                ->color('gray');
        }

        $brecha = (float) $conProyeccion->avg('brecha_rendimiento');
        $puntos = $brecha * 100;

        // ¿Qué explica más la diferencia?
        $causas = [
            'el auto parado'                 => (float) $analisis->sum('perdida_ociosidad'),
            'cartera no cobrada'             => (float) $analisis->sum('perdida_cartera'),
            'ventas por debajo de lo estimado' => abs((float) $analisis->where('diferencia_residual', '<', 0)->sum('diferencia_residual')),
            'gastos que absorbió la arrendadora' => (float) $analisis->sum('egresos_totales'),
        ];

        arsort($causas);
        $principal = array_key_first($causas);
        $monto     = $causas[$principal];

        return Stat::make(
            'Brecha proyectado vs. real',
            ($puntos >= 0 ? '+' : '') . number_format($puntos, 1) . ' pts'
        )
            ->description($monto > 0
                ? 'Pesa más: ' . $principal . ' ($' . number_format($monto, 0) . ')'
                : 'Sin desviaciones registradas')
            ->descriptionIcon($puntos >= 0 ? 'heroicon-m-arrow-up-right' : 'heroicon-m-arrow-down-right')
            ->color(match (true) {
                $puntos >= 0   => 'success',
                $puntos >= -5  => 'warning',
                default        => 'danger',
            });
    }

    /**
     * Cuánto capital depende de vender los autos al valor estimado.
     * Con los costos fuera de la ecuación, éste es el riesgo grande.
     */
    protected function riesgoResidual(): Stat
    {
        $expuesto = (float) Unidad::activas()->sum('valor_residual_estimado');

        $vendidasDebajo = Unidad::query()
            ->whereNotNull('valor_venta_real')
            ->whereColumn('valor_venta_real', '<', 'valor_residual_estimado')
            ->count();

        $vendidas = Unidad::whereNotNull('valor_venta_real')->count();

        return Stat::make('Riesgo de valor residual', '$' . number_format($expuesto, 0))
            ->description($vendidas === 0
                ? 'Capital que depende del precio de reventa · aún no se vende ninguna'
                : "{$vendidasDebajo} de {$vendidas} vendidas quedaron por debajo de lo estimado")
            ->descriptionIcon('heroicon-m-banknotes')
            ->color(match (true) {
                $vendidas === 0                 => 'info',
                $vendidasDebajo === 0           => 'success',
                $vendidasDebajo / $vendidas > 0.5 => 'danger',
                default                         => 'warning',
            });
    }
}
