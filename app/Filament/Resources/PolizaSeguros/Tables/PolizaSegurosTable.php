<?php

namespace App\Filament\Resources\PolizaSeguros\Tables;

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

class PolizaSegurosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Evita N+1: sin esto cada fila consulta unidad y aseguradora aparte
            ->modifyQueryUsing(fn ($query) => $query->with([
                'unidad.submarca.marca',
                // descripcion también toca color: sin esto hay un lazy load por fila
                'unidad.color',
                'aseguradora',
            ]))

            ->columns([
                /**
                 * Identificador principal: número de póliza arriba,
                 * la aseguradora que la emitió abajo.
                 */
                TextColumn::make('numero_poliza')
                    ->label('Póliza')
                    ->weight('bold')
                    ->description(fn ($record) => $record->aseguradora?->nombre ?? 'Sin aseguradora')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                TextColumn::make('unidad.codigo_interno')
                    ->label('Vehículo')
                    ->description(fn ($record) => $record->unidad?->descripcion)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('tipo_cobertura')
                    ->label('Cobertura')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'amplia'   => 'Amplia',
                        'limitada' => 'Limitada',
                        'rc'       => 'Resp. civil',
                        default    => ucfirst($state),
                    }),

                TextColumn::make('prima_total')
                    ->label('Prima')
                    ->money('MXN')
                    ->description(fn ($record) => match ($record->forma_pago) {
                        'contado'    => 'De contado',
                        'semestral'  => 'Semestral',
                        'trimestral' => 'Trimestral',
                        'mensual'    => 'Mensual',
                        default      => null,
                    })
                    ->sortable(),

                /**
                 * COLUMNA DE DECISIÓN.
                 * Los días que faltan es lo que hace actuar: rojo si ya
                 * venció, ámbar si queda menos de un mes para renovar.
                 */
                TextColumn::make('dias_para_vencer')
                    ->label('Vence')
                    ->getStateUsing(fn ($record) => (int) now()->startOfDay()
                        ->diffInDays($record->vigencia_fin, false))
                    ->formatStateUsing(fn (int $state) => match (true) {
                        $state < 0  => abs($state) . ' días vencida',
                        $state === 0 => 'Vence hoy',
                        default     => "En {$state} días",
                    })
                    ->description(fn ($record) => $record->vigencia_fin->format('d/M/Y'))
                    ->badge()
                    ->color(fn (int $state) => match (true) {
                        $state < 0  => 'danger',
                        $state < 30 => 'warning',
                        default     => 'success',
                    })
                    ->sortable(query: fn ($query, string $direction) => $query
                        ->orderBy('vigencia_fin', $direction)),

                TextColumn::make('estatus')
                    ->label('Situación')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'vigente'    => 'success',
                        'por_vencer' => 'warning',
                        'vencida'    => 'danger',
                        'cancelada'  => 'gray',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst(str_replace('_', ' ', $state)))
                    ->sortable(),
            ])

            ->filters([
                // Binario: toggle, no select.
                Filter::make('por_vencer')
                    ->label('Vencen en 30 días')
                    ->query(fn ($query) => $query
                        ->whereBetween('vigencia_fin', [now(), now()->addDays(30)]))
                    ->toggle(),

                Filter::make('vencidas')
                    ->label('Ya vencidas')
                    ->query(fn ($query) => $query->where('vigencia_fin', '<', now()))
                    ->toggle(),

                SelectFilter::make('aseguradora_id')
                    ->label('Aseguradora')
                    ->relationship('aseguradora', 'nombre')
                    ->searchable()
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

            ->defaultSort('vigencia_fin', 'asc')
            ->striped()
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Sin pólizas registradas')
            ->emptyStateDescription('Captura la póliza de cada vehículo: sin seguro vigente no se puede activar un contrato.');
    }
}
