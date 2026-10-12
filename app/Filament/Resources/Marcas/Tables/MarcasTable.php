<?php

namespace App\Filament\Resources\Marcas\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Catálogo de marcas.
 *
 * Lo que se pregunta aquí no es "qué marcas existen" sino "cuáles estoy
 * usando de verdad": por eso la columna que manda es cuántos modelos y
 * cuántas unidades cuelgan de cada una.
 */
class MarcasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Los conteos se resuelven en la misma consulta: sin esto son
            // dos consultas extra por fila.
            ->modifyQueryUsing(fn ($query) => $query->withCount(['submarcas', 'unidades']))

            ->columns([
                TextColumn::make('nombre')
                    ->label('Marca')
                    ->weight('bold')
                    ->description(fn ($record) => $record->pais_origen)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('submarcas_count')
                    ->label('Modelos')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'info' : 'gray')
                    ->formatStateUsing(fn ($state) => $state == 1 ? '1 modelo' : "{$state} modelos")
                    ->sortable(),

                TextColumn::make('unidades_count')
                    ->label('En la flota')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => match (true) {
                        $state == 0 => 'Sin unidades',
                        $state == 1 => '1 unidad',
                        default     => "{$state} unidades",
                    })
                    ->sortable(),

                TextColumn::make('activo')
                    ->label('Situación')
                    ->badge()
                    ->state(fn ($record) => $record->activo ? 'Activa' : 'Inactiva')
                    ->color(fn ($record) => $record->activo ? 'success' : 'gray'),
            ])

            ->filters([
                SelectFilter::make('activo')
                    ->label('Situación')
                    ->options([1 => 'Activas', 0 => 'Inactivas']),

                Filter::make('sin_modelos')
                    ->label('Sin modelos dados de alta')
                    ->query(fn ($query) => $query->doesntHave('submarcas'))
                    ->toggle(),
            ])

            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('Ver detalle'),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->tooltip('Más acciones'),
            ])

            ->defaultSort('nombre')
            ->striped()
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Sin marcas')
            ->emptyStateDescription('Las marcas agrupan a los modelos: Audi → Q3, Q5, A4.');
    }
}
