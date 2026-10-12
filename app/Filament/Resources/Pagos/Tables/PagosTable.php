<?php

namespace App\Filament\Resources\Pagos\Tables;

use App\Filament\Resources\Pagos\Actions\AplicarPagoAction;
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

class PagosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Evita N+1: sin esto cada fila consulta cliente y contrato aparte
            ->modifyQueryUsing(fn ($query) => $query
                ->with([
                    'arrendatario',
                    'contrato',
                ])
                // withSum en vez de llamar montoDisponible() por fila: ese
                // método hace su propio sum() y provocaría una consulta por
                // renglón. Aquí sale en la misma query, con igual semántica.
                ->withSum('aplicaciones as aplicado_sum', 'monto'))

            ->columns([
                /**
                 * Identificador principal: folio arriba,
                 * fecha en que entró el dinero abajo.
                 */
                TextColumn::make('folio')
                    ->label('Pago')
                    ->weight('bold')
                    ->description(fn ($record) => $record->fecha_pago->format('d/M/Y'))
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                TextColumn::make('arrendatario.nombre_completo')
                    ->label('Cliente')
                    ->description(fn ($record) => $record->contrato?->folio)
                    ->searchable(query: fn ($query, string $search) => $query
                        ->whereHas('arrendatario', fn ($q) => $q
                            ->where('nombre', 'ilike', "%{$search}%")
                            ->orWhere('apellido_paterno', 'ilike', "%{$search}%")
                            ->orWhere('razon_social', 'ilike', "%{$search}%")))
                    ->wrap(),

                TextColumn::make('monto')
                    ->label('Monto')
                    ->money('MXN')
                    ->weight('bold')
                    ->description(fn ($record) => match ($record->metodo_pago) {
                        'transferencia' => 'Transferencia',
                        'deposito'      => 'Depósito',
                        'efectivo'      => 'Efectivo',
                        'cheque'        => 'Cheque',
                        'tarjeta'       => 'Tarjeta',
                        'domiciliacion' => 'Domiciliación',
                        default         => 'Otro método',
                    })
                    ->sortable(),

                TextColumn::make('referencia')
                    ->label('Referencia')
                    ->description(fn ($record) => $record->banco_origen)
                    ->placeholder('Sin referencia')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                /**
                 * COLUMNA DE DECISIÓN.
                 * Un pago que entró pero no se aplicó es dinero sin acreditar:
                 * el cliente aparece debiendo aunque ya haya pagado.
                 */
                TextColumn::make('sin_aplicar')
                    ->label('Sin aplicar')
                    ->getStateUsing(fn ($record) => round(
                        (float) $record->monto - (float) ($record->aplicado_sum ?? 0),
                        2
                    ))
                    ->money('MXN')
                    ->weight('bold')
                    ->description(fn ($record) => ((float) $record->monto - (float) ($record->aplicado_sum ?? 0)) > 0
                        ? 'Falta acreditarlo'
                        : 'Todo aplicado')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),

                TextColumn::make('estatus')
                    ->label('Situación')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'aplicado'   => 'success',
                        'confirmado' => 'info',
                        'registrado' => 'warning',
                        'reversado'  => 'danger',
                        'rechazado'  => 'danger',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->sortable(),
            ])

            ->filters([
                // Binarios: toggle, no select.
                Filter::make('sin_aplicar')
                    ->label('Con saldo sin aplicar')
                    ->query(fn ($query) => $query->whereColumn('monto_aplicado', '<', 'monto'))
                    ->toggle(),

                Filter::make('con_saldo_a_favor')
                    ->label('Con saldo a favor')
                    ->query(fn ($query) => $query->where('saldo_a_favor', '>', 0))
                    ->toggle(),

                Filter::make('vigentes')
                    ->label('Ocultar reversados y rechazados')
                    ->query(fn ($query) => $query->vigentes())
                    ->toggle(),

                SelectFilter::make('estatus')
                    ->label('Situación')
                    ->options([
                        'registrado' => 'Registrado',
                        'confirmado' => 'Confirmado',
                        'aplicado'   => 'Aplicado',
                        'reversado'  => 'Reversado',
                        'rechazado'  => 'Rechazado',
                    ])
                    ->multiple(),

                TrashedFilter::make(),
            ])
            ->filtersFormColumns(3)

            ->recordActions([
                AplicarPagoAction::make()->button()->size('sm'),

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

            ->defaultSort('fecha_pago', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Sin pagos registrados')
            ->emptyStateDescription('Registra aquí cada pago que entre y aplícalo a las exhibiciones del contrato correspondiente.');
    }
}
