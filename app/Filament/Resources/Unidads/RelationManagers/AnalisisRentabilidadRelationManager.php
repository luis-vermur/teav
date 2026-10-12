<?php

namespace App\Filament\Resources\Unidads\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Historial de rentabilidad de la unidad.
 *
 * Los cálculos los genera el comando diario `teav:rentabilidad`. Verlos en
 * serie es lo que permite notar si el rendimiento se está deteriorando y
 * por qué: la brecha contra lo proyectado se explica con ociosidad,
 * cartera no cobrada o venta por debajo de lo estimado.
 *
 * Información sensible del negocio: sólo con `ver_rentabilidad`.
 */
class AnalisisRentabilidadRelationManager extends RelationManager
{
    protected static string $relationship = 'analisisRentabilidad';

    protected static ?string $title = 'Rentabilidad';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('ver_rentabilidad') ?? false;
    }

    /** Los genera el comando: no se capturan a mano. */
    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('fecha_calculo')
            ->columns([
                TextColumn::make('fecha_calculo')
                    ->label('Calculado el')
                    ->date('d/M/Y')
                    ->sortable(),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'proyeccion' ? 'Proyección' : 'Real')
                    ->color(fn (string $state) => $state === 'proyeccion' ? 'info' : 'success'),

                TextColumn::make('rendimiento_anual')
                    ->label('Rendimiento anual')
                    ->formatStateUsing(fn ($state) => number_format((float) $state * 100, 2) . '%')
                    ->weight('bold')
                    ->color(fn ($state) => match (true) {
                        (float) $state >= 0.25 => 'success',
                        (float) $state >= 0.15 => 'warning',
                        default                => 'danger',
                    }),

                /**
                 * COLUMNA DE DECISIÓN.
                 * Cuánto se separó la realidad de lo proyectado.
                 */
                TextColumn::make('brecha_rendimiento')
                    ->label('Brecha')
                    ->formatStateUsing(fn ($state) => blank($state)
                        ? null
                        : (($state >= 0 ? '+' : '') . number_format((float) $state * 100, 1) . ' pts'))
                    ->placeholder('Sin proyección')
                    ->color(fn ($state) => blank($state) ? 'gray' : ((float) $state >= 0 ? 'success' : 'danger')),

                TextColumn::make('perdida_ociosidad')
                    ->label('Por ociosidad')
                    ->money('MXN')
                    ->toggleable(),

                TextColumn::make('perdida_cartera')
                    ->label('Cartera no cobrada')
                    ->money('MXN')
                    ->toggleable(),

                TextColumn::make('egresos_totales')
                    ->label('Egresos')
                    ->money('MXN')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(['proyeccion' => 'Proyección', 'real' => 'Real']),
            ])
            ->defaultSort('fecha_calculo', 'desc')
            ->paginated([10, 25])
            ->emptyStateHeading('Sin cálculos todavía')
            ->emptyStateDescription('Se generan con el comando diario teav:rentabilidad.');
    }
}
