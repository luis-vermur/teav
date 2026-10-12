<?php

namespace App\Filament\Resources\Cotizacions\Tables;

use App\Services\CotizacionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Resources\Cotizacions\Actions\ConvertirACotizacionAction;
use App\Filament\Resources\Cotizacions\Actions\DescargarCotizacionAction;
use Filament\Tables\Table;

class CotizacionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // El accesor vehiculo usa la unidad si existe, o la submarca si
            // el auto todavía no se compra: se cargan las dos rutas.
            ->modifyQueryUsing(fn ($query) => $query->with([
                'unidad.submarca.marca',
                'unidad.color',
                'submarca.marca',
                'arrendatario',
            ]))

            ->columns([
                /**
                 * Folio arriba, tipo de cotización abajo: son dos datos que
                 * siempre se leen juntos y ocupaban dos columnas.
                 */
                TextColumn::make('folio')
                    ->label('Cotización')
                    ->weight('bold')
                    ->description(fn ($record) => $record->proposito === 'comercial'
                        ? 'Comercial'
                        : 'Análisis de compra')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('vehiculo')
                    ->label('Vehículo')
                    ->description(fn ($record) => $record->destinatario)
                    ->searchable(query: fn ($query, string $search) => $query
                        ->where('destinatario', 'ilike', "%{$search}%"))
                    ->wrap(),

                // Renta y plazo describen una sola cosa: la mensualidad.
                TextColumn::make('renta_con_iva')
                    ->label('Renta')
                    ->money('MXN')
                    ->description(fn ($record) => "{$record->plazo_meses} meses")
                    ->sortable(),

                TextColumn::make('rendimiento_anual')
                    ->label('Rend. anual')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 0.25 => 'success',
                        $state >= 0.15 => 'warning',
                        default        => 'danger',
                    })
                    ->formatStateUsing(fn ($state) => number_format($state * 100, 1) . '%')
                    ->sortable(),

                /**
                 * El estatus y la vigencia se contradicen a menudo: una
                 * cotización "enviada" que ya venció sigue diciendo enviada.
                 * La fecha va como descripción para que se lean juntas.
                 */
                TextColumn::make('estatus')
                    ->label('Situación')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'aceptada', 'convertida' => 'success',
                        'enviada'   => 'info',
                        'rechazada' => 'danger',
                        'vencida'   => 'gray',
                        default     => 'warning',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->description(fn ($record) => $record->vigencia_hasta
                        ? ($record->estaVigente()
                            ? 'Vence ' . $record->vigencia_hasta->format('d/M/Y')
                            : 'Venció ' . $record->vigencia_hasta->format('d/M/Y'))
                        : null)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('proposito')
                    ->label('Tipo')
                    ->options([
                        'analisis_compra' => 'Análisis de compra',
                        'comercial'       => 'Comercial',
                    ]),

                SelectFilter::make('estatus')
                    ->options([
                        'borrador'   => 'Borrador',
                        'enviada'    => 'Enviada',
                        'aceptada'   => 'Aceptada',
                        'rechazada'  => 'Rechazada',
                        'convertida' => 'Convertida',
                        'vencida'    => 'Vencida',
                    ])
                    ->multiple(),
            ])
            ->filtersFormColumns(2)

            ->recordActions([
                DescargarCotizacionAction::make()->button()->size('sm'),

                ConvertirACotizacionAction::make(),

                ActionGroup::make([
                    ViewAction::make()->label('Ver detalle'),
                    EditAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->tooltip('Más acciones'),
            ])
            ->defaultSort('folio', 'desc')
            ->striped()
            ->emptyStateHeading('Sin cotizaciones')
            ->emptyStateDescription('Crea un análisis de compra o una propuesta para un cliente.');
    }
}