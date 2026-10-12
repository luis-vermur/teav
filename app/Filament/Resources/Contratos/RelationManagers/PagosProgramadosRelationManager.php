<?php

namespace App\Filament\Resources\Contratos\RelationManagers;

use App\Services\PagoService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class PagosProgramadosRelationManager extends RelationManager
{
    protected static string $relationship = 'pagosProgramados';

    protected static ?string $title = 'Calendario de pagos';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero_exhibicion')
            ->columns([
                TextColumn::make('numero_exhibicion')
                    ->label('#')
                    ->sortable()
                    ->width('60px'),

                /**
                 * El calendario ya no es sólo rentas: distinguir el concepto
                 * de un vistazo es lo que evita confundir el cargo del seguro
                 * con una renta atrasada.
                 */
                TextColumn::make('concepto')
                    ->label('Concepto')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'renta', 'renta_proporcional' => 'gray',
                        'seguro'                      => 'info',
                        'gastos_iniciales'            => 'purple',
                        default                       => 'warning',
                    })
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'renta'              => 'Renta',
                        'renta_proporcional' => 'Renta parcial',
                        'seguro'             => 'Seguro',
                        'gastos_iniciales'   => 'Gastos iniciales',
                        default              => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->sortable(),

                TextColumn::make('fecha_vencimiento')
                    ->label('Vence')
                    ->date('d/M/Y')
                    ->sortable(),

                TextColumn::make('fecha_limite')
                    ->label('Límite')
                    ->date('d/M/Y')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('monto_total')
                    ->label('Monto')
                    ->money('MXN')
                    // "Del 17 al 31 de agosto (15 días)" bajo la cifra
                    ->description(fn($record) => $record->descripcion)
                    ->wrap(),

                TextColumn::make('monto_aplicado')
                    ->label('Pagado')
                    ->money('MXN')
                    ->color('success'),

                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->money('MXN')
                    ->weight('bold')
                    ->color(fn($record) => $record->saldo > 0 ? 'danger' : 'gray'),

                TextColumn::make('estatus')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'pagado'    => 'success',
                        'parcial'   => 'warning',
                        'vencido'   => 'danger',
                        'condonado' => 'gray',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => ucfirst($state)),

                TextColumn::make('dias_atraso')
                    ->label('Atraso')
                    ->suffix(' días')
                    ->color('danger')
                    ->placeholder('—')
                    ->formatStateUsing(fn($state) => $state > 0 ? $state : null),
            ])
            ->filters([
                SelectFilter::make('estatus')->options([
                    'pendiente' => 'Pendiente',
                    'parcial' => 'Parcial',
                    'pagado' => 'Pagado',
                    'vencido' => 'Vencido',
                ])->multiple(),

                SelectFilter::make('concepto')
                    ->label('Concepto')
                    ->options([
                        'renta'              => 'Renta',
                        'renta_proporcional' => 'Renta parcial',
                        'seguro'             => 'Seguro',
                        'gastos_iniciales'   => 'Gastos iniciales',
                        'otro'               => 'Otro',
                    ])->multiple(),
            ])
            ->headerActions([
                Action::make('registrarPago')
                    ->label('Registrar pago')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn() => $this->getOwnerRecord()->estaVigente())
                    ->schema([
                        TextInput::make('monto')
                            ->label('Monto recibido')
                            ->numeric()->prefix('$')->required()
                            ->default(fn() => $this->getOwnerRecord()->renta_con_iva)
                            ->helperText('Se aplica primero a penalizaciones, luego a las exhibiciones más antiguas'),

                        DatePicker::make('fecha_pago')
                            ->label('Fecha del pago')
                            ->default(now())->required(),

                        Select::make('metodo_pago')
                            ->label('Método')
                            ->options([
                                'transferencia' => 'Transferencia',
                                'deposito' => 'Depósito',
                                'efectivo' => 'Efectivo',
                                'cheque' => 'Cheque',
                                'tarjeta' => 'Tarjeta',
                                'domiciliacion' => 'Domiciliación',
                                'otro' => 'Otro',
                            ])->default('transferencia')->required(),

                        TextInput::make('referencia')
                            ->label('Referencia / clave de rastreo'),

                        TextInput::make('banco_origen')->label('Banco de origen'),

                        Textarea::make('notas')->rows(2),
                    ])
                    ->action(function (array $data) {
                        $contrato = $this->getOwnerRecord();

                        $pago = app(PagoService::class)->registrar($contrato, $data);

                        Notification::make()
                            ->title("Pago {$pago->folio} registrado")
                            ->body($pago->saldo_a_favor > 0
                                ? 'Quedó un saldo a favor de $' . number_format($pago->saldo_a_favor, 2)
                                : 'Aplicado correctamente.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('condonar')
                    ->label('Condonar')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn($record) => $record->saldo > 0
                        && Gate::allows('condonar_adeudo'))
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('motivo')->label('Motivo')->required()->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'estatus' => 'condonado',
                            'saldo'   => 0,
                            'notas'   => 'Condonado: ' . $data['motivo'],
                        ]);

                        Notification::make()->title('Exhibición condonada')->success()->send();
                    }),
            ])
            ->defaultSort('numero_exhibicion')
            ->paginated([12, 24, 48]);
    }
}
