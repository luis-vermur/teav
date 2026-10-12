<?php

namespace App\Filament\Pages;

use App\Models\Arrendatario;
use App\Models\Unidad;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Dashboard as BaseDashboard;
use BackedEnum;
use UnitEnum;

/**
 * Inicio orientado a TAREAS, no a datos.
 *
 * Lo primero que ve el usuario son los botones de lo que hace todos
 * los días, no una lista de tablas. Los indicadores quedan debajo,
 * como contexto, no como protagonistas.
 */
class Inicio extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Inicio';

    protected static ?string $title = '¿Qué quieres hacer hoy?';

    protected static ?int $navigationSort = -10;

    protected static ?string $slug = 'inicio';

    public function getHeaderActions(): array
    {
        return [
            Action::make('nuevo_contrato')
                ->label('Nuevo contrato')
                ->icon('heroicon-m-document-plus')
                ->color('primary')
                ->size('lg')
                ->url(fn() => \App\Filament\Resources\Contratos\ContratoResource::getUrl('create')),

            /**
             * Apuntaba al listado de contratos, de modo que el botón decía
             * "Registrar pago" y dejaba al usuario frente a una tabla sin
             * decirle qué hacer. El alta de pagos tiene su propia pantalla.
             */
            Action::make('registrar_pago')
                ->label('Registrar pago')
                ->icon('heroicon-m-banknotes')
                ->color('gray')->outlined()
                ->size('lg')
                ->url(fn() => \App\Filament\Resources\Pagos\PagoResource::getUrl('create')),

            Action::make('cotizar')
                ->label('Cotizar')
                ->icon('heroicon-m-calculator')
                ->color('gray')
                ->size('lg')
                ->url(fn() => \App\Filament\Resources\Cotizacions\CotizacionResource::getUrl('create')),

            ActionGroup::make([
                Action::make('nuevo_cliente')
                    ->label('Dar de alta un cliente')
                    ->icon('heroicon-m-user-plus')
                    ->url(fn() => \App\Filament\Resources\Arrendatarios\ArrendatarioResource::getUrl('create')),

                Action::make('nuevo_vehiculo')
                    ->label('Dar de alta un vehículo')
                    ->icon('heroicon-m-truck')
                    ->url(fn() => \App\Filament\Resources\Unidads\UnidadResource::getUrl('create')),

                Action::make('registrar_servicio')
                    ->label('Registrar un servicio')
                    ->icon('heroicon-m-wrench-screwdriver')
                    ->url(fn() => \App\Filament\Resources\Mantenimientos\MantenimientoResource::getUrl('create')),
            ])
                ->label('Más')
                ->icon('heroicon-m-plus')
                ->button(),
        ];
    }

    public function getSubheading(): ?string
    {
        $disponibles = Unidad::disponibles()->count();
        $prospectos  = Arrendatario::where('estatus', 'prospecto')->count();

        $partes = [];

        if ($disponibles > 0) {
            $partes[] = "{$disponibles} vehículo(s) disponible(s) para rentar";
        }

        if ($prospectos > 0) {
            $partes[] = "{$prospectos} prospecto(s) sin convertir";
        }

        return $partes ? implode(' · ', $partes) : null;
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\ResumenFlota::class,
            \App\Filament\Widgets\Alertas::class,
            \App\Filament\Widgets\CobranzaUrgente::class,
        ];
    }

    public function getColumns(): int | array
    {
        return 4;
    }
}
