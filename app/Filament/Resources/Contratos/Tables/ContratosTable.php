<?php

namespace App\Filament\Resources\Contratos\Tables;

use App\Filament\Resources\Contratos\Actions\ActivarContratoAction;
use App\Services\ContratoService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class ContratosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Evita N+1: sin esto cada fila dispara 3 consultas extra
            ->modifyQueryUsing(fn ($query) => $query->with([
                'arrendatario',
                'unidad.submarca.marca',
                // El accesor descripcion también toca color: sin esto hay
                // una consulta extra por cada fila de la tabla.
                'unidad.color',
            ]))

            ->columns([
                /**
                 * Columna compuesta: folio arriba, fecha de inicio abajo.
                 * Dos datos en el espacio de uno.
                 */
                TextColumn::make('folio')
                    ->label('Contrato')
                    ->weight('bold')
                    ->description(fn ($record) => 'Desde ' . $record->fecha_inicio->format('d/M/Y'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('arrendatario.nombre_completo')
                    ->label('Cliente')
                    ->description(fn ($record) => $record->arrendatario->codigo)
                    ->searchable(query: fn ($query, string $search) => $query
                        ->whereHas('arrendatario', fn ($q) => $q
                            ->where('nombre', 'ilike', "%{$search}%")
                            ->orWhere('apellido_paterno', 'ilike', "%{$search}%")
                            ->orWhere('razon_social', 'ilike', "%{$search}%")))
                    ->wrap(),

                TextColumn::make('unidad.codigo_interno')
                    ->label('Vehículo')
                    ->description(fn ($record) => $record->unidad->descripcion)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('renta_con_iva')
                    ->label('Renta')
                    ->money('MXN')
                    ->description(fn ($record) => "{$record->plazo_meses} meses · día {$record->dia_corte}")
                    ->sortable(),

                /**
                 * Avance de pago en texto simple.
                 * Más útil que una barra: dice cuánto falta, no sólo cuánto va.
                 */
                TextColumn::make('avance')
                    ->label('Avance')
                    ->getStateUsing(fn ($record) => $record->exhibicionesPagadas() . '/' . $record->plazo_meses)
                    ->description(fn ($record) => number_format($record->avance(), 0) . '% pagado')
                    ->badge()
                    ->color(fn ($record) => match (true) {
                        $record->avance() >= 75 => 'success',
                        $record->avance() >= 25 => 'info',
                        default                 => 'gray',
                    }),

                TextColumn::make('saldo_vencido')
                    ->label('Vencido')
                    ->getStateUsing(fn ($record) => $record->saldoVencido())
                    ->money('MXN')
                    ->weight('bold')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->placeholder('Al corriente'),

                TextColumn::make('estatus')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'vigente'           => 'success',
                        'por_vencer'        => 'warning',
                        'en_incumplimiento' => 'danger',
                        'finalizado'        => 'gray',
                        'rescindido'        => 'danger',
                        default             => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst(str_replace('_', ' ', $state)))
                    ->sortable(),
            ])

            ->filters([
                SelectFilter::make('estatus')
                    ->options([
                        'borrador'          => 'Borrador',
                        'vigente'           => 'Vigente',
                        'por_vencer'        => 'Por vencer',
                        'en_incumplimiento' => 'En incumplimiento',
                        'finalizado'        => 'Finalizado',
                    ])
                    ->multiple(),

                Filter::make('con_adeudo')
                    ->label('Sólo con adeudo')
                    ->query(fn ($query) => $query->conAdeudo())
                    ->toggle(),

                Filter::make('por_vencer_pronto')
                    ->label('Vencen en 60 días')
                    ->query(fn ($query) => $query->porVencer(60))
                    ->toggle(),
            ])
            ->filtersFormColumns(3)

            /**
             * Acciones: las importantes como botón visible,
             * las de siempre agrupadas en el menú.
             */
            ->recordActions([
                ActivarContratoAction::make()->button()->size('sm'),

                Action::make('cerrar')
                    ->label('Cerrar')
                    ->icon('heroicon-m-flag')
                    ->color('warning')
                    ->button()
                    ->size('sm')
                    ->visible(fn ($record) => $record->estaVigente() && Gate::allows('cerrar_contrato'))
                    ->modalHeading('Cerrar contrato')
                    ->modalWidth('2xl')
                    ->schema([
                        Select::make('tipo_cierre')
                            ->label('Tipo de cierre')
                            ->options([
                                'termino_natural'      => 'Término natural del plazo',
                                'venta_arrendatario'   => 'Venta al arrendatario',
                                'venta_tercero'        => 'Venta a un tercero',
                                'devolucion'           => 'Devolución de la unidad',
                                'rescision_anticipada' => 'Rescisión anticipada',
                                'perdida_total'        => 'Pérdida total',
                            ])
                            ->required()
                            ->live(),

                        DatePicker::make('fecha_cierre')->label('Fecha')->default(now())->required(),

                        TextInput::make('kilometraje_final')
                            ->label('Kilometraje final')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->default(fn ($record) => $record->unidad->kilometraje_actual),

                        TextInput::make('monto_venta')
                            ->label('Monto de la venta')
                            ->numeric()->prefix('$')
                            ->required(fn (Get $get) => str_starts_with($get('tipo_cierre') ?? '', 'venta'))
                            ->visible(fn (Get $get) => str_starts_with($get('tipo_cierre') ?? '', 'venta')),

                        TextInput::make('comprador_nombre')
                            ->label('Nombre del comprador')
                            ->visible(fn (Get $get) => $get('tipo_cierre') === 'venta_tercero'),

                        TextInput::make('cargo_daños')->label('Cargo por daños')
                            ->numeric()->prefix('$')->default(0),

                        TextInput::make('costo_acondicionamiento')->label('Acondicionamiento')
                            ->numeric()->prefix('$')->default(0),

                        Textarea::make('inventario_entrega')
                            ->label('Inventario recibido')
                            ->rows(2)
                            ->placeholder('Llaves, herramienta, llanta de refacción, tapetes...')
                            ->columnSpanFull(),

                        Textarea::make('observaciones')->rows(2)->columnSpanFull(),

                        Placeholder::make('resumen')
                            ->label('Liquidación estimada')
                            ->content(function (Get $get, $record) {
                                $km = (int) ($get('kilometraje_final') ?: $record->unidad->kilometraje_actual);
                                $p  = app(ContratoService::class)->previsualizarCierre($record, $km);
                                $f  = fn ($n) => '$' . number_format($n, 2);

                                return new HtmlString(
                                    "<div style='font-size:14px;line-height:1.9'>"
                                    . "Km excedente: <b>{$p['km_excedente']}</b> · Cargo: <b>{$f($p['cargo_km_excedente'])}</b><br>"
                                    . "Adeudo pendiente: <b>{$f($p['adeudo_pendiente'])}</b><br>"
                                    . "Depósito disponible: <b>{$f($p['deposito_disponible'])}</b><br>"
                                    . "<span style='color:#059669'>Se devuelve: <b>{$f($p['devolucion_estimada'])}</b></span>"
                                    . ($p['saldo_a_cargo'] > 0
                                        ? "<br><span style='color:#dc2626'>Queda a cargo del cliente: <b>{$f($p['saldo_a_cargo'])}</b></span>"
                                        : '')
                                    . "</div>"
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(ContratoService::class)->cerrar($record, $data);

                            Notification::make()
                                ->title('Contrato cerrado')
                                ->body('La unidad quedó actualizada y el depósito liquidado.')
                                ->success()
                                ->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('No se pudo cerrar')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                // Las de rutina van agrupadas para no saturar la fila
                ActionGroup::make([
                    ViewAction::make()->label('Ver detalle'),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->tooltip('Más acciones'),
            ])

            ->defaultSort('folio', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Sin contratos')
            ->emptyStateDescription('Crea uno desde cero o conviértelo desde una cotización aceptada.');
    }
}
