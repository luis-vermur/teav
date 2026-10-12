<?php

namespace App\Filament\Resources\Mantenimientos\Tables;

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

class MantenimientosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Evita N+1: sin esto cada fila consulta unidad, tipo y taller aparte
            ->modifyQueryUsing(fn ($query) => $query->with([
                'unidad.submarca.marca',
                // descripcion también toca color: sin esto hay un lazy load por fila
                'unidad.color',
                'tipo',
                'proveedor',
            ]))

            ->columns([
                /**
                 * Identificador principal: qué servicio fue,
                 * con la fecha en que se hizo abajo.
                 */
                TextColumn::make('tipo.nombre')
                    ->label('Servicio')
                    ->weight('bold')
                    ->description(fn ($record) => $record->fecha->format('d/M/Y'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('unidad.codigo_interno')
                    ->label('Vehículo')
                    ->description(fn ($record) => $record->unidad?->descripcion)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('proveedor.nombre')
                    ->label('Taller')
                    ->description(fn ($record) => $record->folio_taller
                        ? "Folio {$record->folio_taller}"
                        : null)
                    ->placeholder('Sin taller')
                    ->searchable()
                    ->wrap(),

                /**
                 * Costo con IVA usando el accesor del modelo, y debajo quién
                 * lo pagó: sólo lo de la arrendadora pega a la rentabilidad.
                 */
                TextColumn::make('costo_total')
                    ->label('Costo')
                    ->getStateUsing(fn ($record) => $record->costo_total)
                    ->money('MXN')
                    ->description(fn ($record) => match ($record->responsable_costo) {
                        'arrendadora'  => 'Lo paga la arrendadora',
                        'arrendatario' => 'Lo paga el cliente',
                        'seguro'       => 'Lo cubre el seguro',
                        'garantia'     => 'Va por garantía',
                        default        => null,
                    })
                    ->color(fn ($record) => $record->responsable_costo === 'arrendadora' ? 'warning' : 'gray')
                    ->sortable(),

                /**
                 * COLUMNA DE DECISIÓN.
                 * El próximo servicio es lo que obliga a agendar: rojo si ya
                 * se pasó la fecha, ámbar si cae dentro de los 30 días.
                 */
                TextColumn::make('proximo_fecha')
                    ->label('Próximo servicio')
                    ->placeholder('Sin programar')
                    // Null-safe: formatState() no protege contra null por su
                    // cuenta, sólo el placeholder corta antes en el render.
                    ->formatStateUsing(fn ($state) => blank($state)
                        ? null
                        : ($state->isPast()
                            ? $state->format('d/M/Y') . ' — vencido'
                            : $state->format('d/M/Y')))
                    ->description(fn ($record) => $record->proximo_kilometraje
                        ? 'o a los ' . number_format($record->proximo_kilometraje) . ' km'
                        : null)
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        blank($state)                      => 'gray',
                        $state->isPast()                   => 'danger',
                        $state->lte(now()->addDays(30))    => 'warning',
                        default                            => 'success',
                    })
                    ->sortable(),

                TextColumn::make('estatus')
                    ->label('Situación')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'completado' => 'success',
                        'en_proceso' => 'info',
                        'programado' => 'warning',
                        'cancelado'  => 'gray',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst(str_replace('_', ' ', $state)))
                    ->sortable(),
            ])

            ->filters([
                // Binarios: toggle, no select.
                Filter::make('servicio_vencido')
                    ->label('Servicio vencido')
                    ->query(fn ($query) => $query->vencidos())
                    ->toggle(),

                Filter::make('proximos')
                    ->label('Próximos 30 días')
                    ->query(fn ($query) => $query->proximos(30))
                    ->toggle(),

                Filter::make('paga_arrendadora')
                    ->label('Sólo los que paga la arrendadora')
                    ->query(fn ($query) => $query->where('responsable_costo', 'arrendadora'))
                    ->toggle(),

                SelectFilter::make('estatus')
                    ->label('Situación')
                    ->options([
                        'programado' => 'Programado',
                        'en_proceso' => 'En proceso',
                        'completado' => 'Completado',
                        'cancelado'  => 'Cancelado',
                    ])
                    ->multiple(),

                TrashedFilter::make(),
            ])
            ->filtersFormColumns(3)

            ->recordActions([
                // Las de rutina van agrupadas para no saturar la fila
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

            ->defaultSort('fecha', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Sin servicios registrados')
            ->emptyStateDescription('Registra cada servicio para llevar el historial de la unidad y saber cuándo toca el siguiente.');
    }
}
