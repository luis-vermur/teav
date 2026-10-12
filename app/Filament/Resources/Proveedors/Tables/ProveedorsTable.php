<?php

namespace App\Filament\Resources\Proveedors\Tables;

use App\Filament\Resources\Proveedors\Schemas\ProveedorForm;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

/**
 * Directorio de proveedores.
 *
 * Se consulta para dos cosas: encontrar a quién llamar y saber a quién se
 * le ha dado trabajo. De ahí las columnas de contacto y de servicios.
 */
class ProveedorsTable
{
    /** Color de cada tipo, igual que en la ficha. */
    private const COLORES = [
        'agencia'      => 'info',
        'taller'       => 'warning',
        'llantera'     => 'gray',
        'gestoria'     => 'purple',
        'verificentro' => 'gray',
        'aseguradora'  => 'success',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('mantenimientos'))

            ->columns([
                TextColumn::make('nombre')
                    ->label('Proveedor')
                    ->weight('bold')
                    ->description(fn ($record) => $record->rfc)
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('tipos')
                    ->label('Qué maneja')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ProveedorForm::TIPOS[$state] ?? ucfirst((string) $state))
                    ->color(fn ($state) => self::COLORES[$state] ?? 'gray')
                    ->placeholder('Sin clasificar'),

                TextColumn::make('telefono')
                    ->label('Contacto')
                    ->icon('heroicon-m-phone')
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->description(fn ($record) => $record->contacto_nombre ?: $record->email)
                    ->placeholder('Sin teléfono')
                    ->color(fn ($state) => blank($state) ? 'warning' : null)
                    ->searchable(),

                TextColumn::make('mantenimientos_count')
                    ->label('Servicios')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'info' : 'gray')
                    ->formatStateUsing(fn ($state) => $state == 0 ? 'Sin trabajos' : $state)
                    ->sortable(),

                TextColumn::make('activo')
                    ->label('Situación')
                    ->badge()
                    ->state(fn ($record) => $record->activo ? 'Activo' : 'Inactivo')
                    ->color(fn ($record) => $record->activo ? 'success' : 'gray'),
            ])

            ->filters([
                SelectFilter::make('tipos')
                    ->label('Qué maneja')
                    ->options(ProveedorForm::TIPOS)
                    // `tipos` es json: no se puede filtrar con un where normal.
                    ->query(fn ($query, array $data) => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereJsonContains('tipos', $data['value'])),

                SelectFilter::make('activo')
                    ->label('Situación')
                    ->options([1 => 'Activos', 0 => 'Inactivos']),

                Filter::make('con_trabajos')
                    ->label('Con servicios registrados')
                    ->query(fn ($query) => $query->has('mantenimientos'))
                    ->toggle(),

                TrashedFilter::make(),
            ])
            ->filtersFormColumns(2)

            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('Ver detalle'),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->tooltip('Más acciones'),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])

            ->defaultSort('nombre')
            ->striped()
            ->paginated([25, 50])
            ->emptyStateHeading('Sin proveedores')
            ->emptyStateDescription('Agencias, talleres, llanteras y gestorías con las que se trabaja.');
    }
}
