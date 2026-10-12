<?php

namespace App\Filament\Resources\Unidads\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UnidadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Evita N+1: descripcion arma su texto con submarca, marca y color.
            ->modifyQueryUsing(fn ($query) => $query->with([
                'submarca.marca',
                'color',
            ]))

            ->columns([
                TextColumn::make('codigo_interno')
                    ->label('Código')->searchable()->sortable(),

                TextColumn::make('submarca.nombre_completo')
                    ->label('Vehículo')
                    ->description(fn($record) => "{$record->anio} · " . ($record->color?->nombre ?? 's/color'))
                    ->searchable(query: fn($query, string $search) => $query
                        ->whereHas('submarca', fn($q) => $q
                            ->where('nombre', 'ilike', "%{$search}%")
                            ->orWhereHas('marca', fn($m) => $m->where('nombre', 'ilike', "%{$search}%")))),

                TextColumn::make('placas')->searchable()->toggleable(),

                TextColumn::make('vin')
                    ->label('VIN')->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),

                TextColumn::make('estatus')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'disponible'    => 'success',
                        'arrendada'     => 'info',
                        'apartada'      => 'warning',
                        'mantenimiento' => 'warning',
                        'siniestrada'   => 'danger',
                        'vendida', 'baja' => 'gray',
                        default         => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => ucfirst(str_replace('_', ' ', $state)))
                    ->sortable(),

                TextColumn::make('precio_adquisicion')
                    ->label('Inversión')
                    ->money('MXN')
                    ->sortable()
                    ->summarize(Sum::make()->money('MXN')->label('Total')),

                TextColumn::make('kilometraje_actual')
                    ->label('Km')
                    ->numeric()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('estatus')->options([
                    'disponible' => 'Disponible',
                    'arrendada' => 'Arrendada',
                    'mantenimiento' => 'En mantenimiento',
                    'siniestrada' => 'Siniestrada',
                    'en_venta' => 'En venta',
                    'vendida' => 'Vendida',
                ])->multiple(),

                SelectFilter::make('submarca')
                    ->relationship('submarca', 'nombre')
                    ->searchable()->preload()->label('Submarca'),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->defaultSort('codigo_interno');
    }
}
