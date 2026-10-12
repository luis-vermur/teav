<?php

namespace App\Filament\Resources\Submarcas\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Catálogo de modelos (submarcas).
 *
 * La tabla mostraba `marca.id` — un número sin significado. Ahora la marca
 * va como descripción del modelo, que es como se lee en la vida real:
 * "Q3 · Audi".
 */
class SubmarcasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('marca')->withCount('unidades'))

            ->columns([
                TextColumn::make('nombre')
                    ->label('Modelo')
                    ->weight('bold')
                    ->description(fn ($record) => $record->marca?->nombre)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('marca.nombre')
                    ->label('Marca')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('segmento')
                    ->label('Segmento')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Sin clasificar')
                    ->sortable(),

                TextColumn::make('unidades_count')
                    ->label('En la flota')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $state == 1 ? '1 unidad' : "{$state} unidades")
                    ->sortable(),

                TextColumn::make('activo')
                    ->label('Situación')
                    ->badge()
                    ->state(fn ($record) => $record->activo ? 'Activo' : 'Inactivo')
                    ->color(fn ($record) => $record->activo ? 'success' : 'gray'),
            ])

            ->filters([
                SelectFilter::make('marca_id')
                    ->label('Marca')
                    ->relationship('marca', 'nombre')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('segmento')
                    ->label('Segmento')
                    ->options(fn () => \App\Models\Submarca::query()
                        ->whereNotNull('segmento')
                        ->distinct()
                        ->orderBy('segmento')
                        ->pluck('segmento', 'segmento')
                        ->all())
                    ->multiple(),

                Filter::make('sin_unidades')
                    ->label('Sin unidades en la flota')
                    ->query(fn ($query) => $query->doesntHave('unidades'))
                    ->toggle(),
            ])
            ->filtersFormColumns(3)

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
            ->emptyStateHeading('Sin modelos')
            ->emptyStateDescription('Cada modelo pertenece a una marca y es lo que se elige al dar de alta un vehículo.');
    }
}
