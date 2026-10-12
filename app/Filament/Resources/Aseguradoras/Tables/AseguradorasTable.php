<?php

namespace App\Filament\Resources\Aseguradoras\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Catálogo de aseguradoras.
 *
 * Lo urgente de una aseguradora es el teléfono de siniestros: es el dato
 * que se busca con el auto chocado en la calle. Va en primer plano, no
 * escondido en una ficha.
 */
class AseguradorasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'polizas',
                'polizas as polizas_vigentes_count' => fn ($q) => $q->where('estatus', 'vigente'),
            ]))

            ->columns([
                TextColumn::make('nombre')
                    ->label('Aseguradora')
                    ->weight('bold')
                    ->description(fn ($record) => $record->rfc)
                    ->searchable()
                    ->sortable(),

                /**
                 * Sin teléfono de siniestros la aseguradora no sirve cuando
                 * más se necesita: la ausencia se avisa en ámbar.
                 */
                TextColumn::make('telefono_siniestros')
                    ->label('Siniestros 24 h')
                    ->icon('heroicon-m-phone')
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->placeholder('Sin teléfono')
                    ->color(fn ($state) => blank($state) ? 'warning' : null)
                    ->searchable(),

                TextColumn::make('contacto_nombre')
                    ->label('Contacto')
                    ->description(fn ($record) => $record->contacto_email)
                    ->placeholder('Sin contacto')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('polizas_vigentes_count')
                    ->label('Pólizas vigentes')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->description(fn ($record) => $record->polizas_count . ' en total')
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

                Filter::make('con_polizas_vigentes')
                    ->label('Con pólizas vigentes')
                    ->query(fn ($query) => $query->whereHas('polizas', fn ($q) => $q->where('estatus', 'vigente')))
                    ->toggle(),

                Filter::make('sin_telefono')
                    ->label('Sin teléfono de siniestros')
                    ->query(fn ($query) => $query->whereNull('telefono_siniestros')->orWhere('telefono_siniestros', ''))
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
            ->paginated([25, 50])
            ->emptyStateHeading('Sin aseguradoras')
            ->emptyStateDescription('Son las que emiten las pólizas de la flota.');
    }
}
