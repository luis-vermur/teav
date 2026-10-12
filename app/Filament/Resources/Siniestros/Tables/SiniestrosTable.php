<?php

namespace App\Filament\Resources\Siniestros\Tables;

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

class SiniestrosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Evita N+1: sin esto cada fila consulta unidad, póliza y cliente aparte
            ->modifyQueryUsing(fn ($query) => $query->with([
                'unidad.submarca.marca',
                // descripcion también toca color: sin esto hay un lazy load por fila
                'unidad.color',
                'poliza.aseguradora',
                'arrendatario',
            ]))

            ->columns([
                /**
                 * Identificador principal: número de reporte arriba,
                 * cuándo ocurrió abajo.
                 */
                TextColumn::make('numero_reporte')
                    ->label('Reporte')
                    ->weight('bold')
                    ->placeholder('Sin número')
                    ->description(fn ($record) => $record->fecha_siniestro?->format('d/M/Y H:i'))
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                TextColumn::make('unidad.codigo_interno')
                    ->label('Vehículo')
                    ->description(fn ($record) => $record->unidad?->descripcion)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'robo_total'     => 'danger',
                        'colision'       => 'warning',
                        'robo_parcial'   => 'warning',
                        'daños_terceros' => 'warning',
                        'cristales'      => 'info',
                        default          => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'colision'       => 'Colisión',
                        'robo_total'     => 'Robo total',
                        'robo_parcial'   => 'Robo parcial',
                        'cristales'      => 'Cristales',
                        'daños_terceros' => 'Daños a terceros',
                        default          => 'Otro',
                    }),

                TextColumn::make('monto_estimado')
                    ->label('Daño estimado')
                    ->money('MXN')
                    ->placeholder('Sin valuar')
                    ->description(fn ($record) => $record->lugar)
                    ->sortable(),

                /**
                 * COLUMNA DE DECISIÓN.
                 * Lo que falta por cobrarle al seguro. Mientras sea mayor a
                 * cero hay dinero en la calle y alguien tiene que perseguirlo.
                 */
                TextColumn::make('por_recuperar')
                    ->label('Por recuperar')
                    ->getStateUsing(fn ($record) => max(
                        0,
                        (float) $record->monto_estimado - (float) $record->monto_recuperado
                    ))
                    ->money('MXN')
                    ->weight('bold')
                    ->description(fn ($record) => filled($record->deducible_pagado)
                        ? 'Deducible: $' . number_format((float) $record->deducible_pagado, 2)
                            . ' · ' . match ($record->responsable_deducible) {
                                'arrendadora'  => 'lo pagó la arrendadora',
                                'arrendatario' => 'lo pagó el cliente',
                                'tercero'      => 'lo pagó un tercero',
                                default        => 'sin responsable',
                            }
                        : null)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                TextColumn::make('estatus')
                    ->label('Trámite')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'reportado'     => 'warning',
                        'en_valuacion'  => 'info',
                        'en_reparacion' => 'info',
                        'cerrado'       => 'success',
                        'perdida_total' => 'danger',
                        default         => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'reportado'     => 'Reportado',
                        'en_valuacion'  => 'En valuación',
                        'en_reparacion' => 'En reparación',
                        'cerrado'       => 'Cerrado',
                        'perdida_total' => 'Pérdida total',
                        default         => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->sortable(),
            ])

            ->filters([
                // Binarios: toggle, no select.
                Filter::make('abiertos')
                    ->label('Sólo trámites abiertos')
                    ->query(fn ($query) => $query->abiertos())
                    ->toggle(),

                Filter::make('con_saldo_por_recuperar')
                    ->label('Con dinero por recuperar')
                    ->query(fn ($query) => $query
                        ->whereNotNull('monto_estimado')
                        ->whereRaw('monto_estimado > COALESCE(monto_recuperado, 0)'))
                    ->toggle(),

                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'colision'       => 'Colisión',
                        'robo_total'     => 'Robo total',
                        'robo_parcial'   => 'Robo parcial',
                        'cristales'      => 'Cristales',
                        'daños_terceros' => 'Daños a terceros',
                        'otro'           => 'Otro',
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

            ->defaultSort('fecha_siniestro', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Sin siniestros')
            ->emptyStateDescription('Ojalá siga así. Cuando ocurra uno, regístralo aquí para dar seguimiento al trámite con la aseguradora.');
    }
}
