<?php

namespace App\Filament\Resources\Arrendatarios\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;

class ArrendatariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Código')->searchable()->sortable(),

                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->searchable(query: fn($query, string $search) => $query
                        ->where('nombre', 'ilike', "%{$search}%")
                        ->orWhere('apellido_paterno', 'ilike', "%{$search}%")
                        ->orWhere('razon_social', 'ilike', "%{$search}%"))
                    ->description(fn($record) => $record->esMoral() ? 'Persona moral' : 'Persona física'),

                TextColumn::make('rfc')->label('RFC')->searchable()->toggleable(),
                TextColumn::make('telefono')->label('Teléfono')->toggleable(),

                TextColumn::make('estatus')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'activo' => 'success',
                        'prospecto', 'en_validacion' => 'warning',
                        'suspendido' => 'danger',
                        'lista_negra' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => ucfirst(str_replace('_', ' ', $state))),

                TextColumn::make('contratos_count')
                    ->counts('contratos')
                    ->label('Contratos')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('tipo_persona')->options([
                    'fisica' => 'Física',
                    'moral' => 'Moral',
                ]),
                SelectFilter::make('estatus')->options([
                    'prospecto' => 'Prospecto',
                    'activo' => 'Activo',
                    'suspendido' => 'Suspendido',
                    'lista_negra' => 'Lista negra',
                ])->multiple(),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->defaultSort('codigo', 'desc');
    }
}
