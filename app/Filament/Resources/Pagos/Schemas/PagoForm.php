<?php

namespace App\Filament\Resources\Pagos\Schemas;

use App\Models\Arrendatario;
use App\Models\Contrato;
use App\Models\Pago;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PagoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            /**
             * Acomodo en pestañas, igual que el formulario de
             * cotización: el alta no debería ser un formulario largo
             * por el que hay que ir bajando.
             */
            Tabs::make('Pago')->columnSpanFull()->tabs([

                Tab::make('De quién')
                    ->icon('heroicon-o-user')
                    ->columns(1)
                    ->schema([
                        /**
                         * Antes se elegía el contrato por su número de id. Ahora
                         * se ve el folio, el cliente y el vehículo, que es como
                         * la gente identifica un contrato.
                         */
                        Select::make('contrato_id')
                            ->label('Contrato')
                            ->options(fn () => Contrato::query()
                                ->with('arrendatario', 'unidad.submarca.marca')
                                ->whereIn('estatus', ['vigente', 'en_incumplimiento', 'por_vencer', 'por_firmar'])
                                ->get()
                                ->mapWithKeys(fn ($c) => [
                                    $c->id => "{$c->folio} — {$c->arrendatario?->nombre_completo} · {$c->unidad?->codigo_interno}",
                                ]))
                            ->searchable()
                            ->required()
                            ->live()
                            ->helperText('Sólo aparecen los contratos que pueden recibir pagos')
                            // El cliente se deduce del contrato: evita cobrarle a quien no es.
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($c = Contrato::find($state)) {
                                    $set('arrendatario_id', $c->arrendatario_id);
                                }
                            })
                            ->columnSpanFull(),

                        Select::make('arrendatario_id')
                            ->label('Cliente')
                            ->options(fn () => Arrendatario::query()
                                ->get()
                                ->mapWithKeys(fn ($a) => [$a->id => "{$a->codigo} — {$a->nombre_completo}"]))
                            ->searchable()
                            ->required()
                            ->helperText(fn (Get $get) => filled($get('contrato_id'))
                                ? 'Se tomó del contrato'
                                : null)
                            ->columnSpanFull(),
                    ]),

                Tab::make('El dinero')
                    ->icon('heroicon-o-banknotes')
                    ->columns(3)
                    ->schema([
                        TextInput::make('monto')
                            ->label('Monto recibido')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            // Un pago negativo no existe: para revertir hay reverso.
                            ->minValue(0.01)
                            ->helperText('Lo que efectivamente entró'),

                        DatePicker::make('fecha_pago')
                            ->label('Fecha del pago')
                            ->default(now())
                            ->maxDate(now())
                            ->required(),

                        Select::make('metodo_pago')
                            ->label('Método de pago')
                            ->options([
                                'transferencia' => 'Transferencia',
                                'deposito'      => 'Depósito',
                                'efectivo'      => 'Efectivo',
                                'cheque'        => 'Cheque',
                                'tarjeta'       => 'Tarjeta',
                                'domiciliacion' => 'Domiciliación',
                                'otro'          => 'Otro',
                            ])
                            ->default('transferencia')
                            ->required()
                            ->live(),

                        TextInput::make('referencia')
                            ->label('Referencia')
                            ->helperText('Clave de rastreo o número de operación'),

                        TextInput::make('banco_origen')
                            ->label('Banco de origen')
                            ->visible(fn (Get $get) => in_array(
                                $get('metodo_pago'),
                                ['transferencia', 'deposito', 'cheque'],
                                true
                            )),

                        TextInput::make('cuenta_destino')
                            ->label('Cuenta destino'),
                    ]),

                Tab::make('Situación')
                    ->icon('heroicon-o-flag')
                    ->columns(1)
                    ->schema([
                        /**
                         * "Aplicado" y "Reversado" NO se pueden elegir aquí.
                         *
                         * Marcarlo como aplicado desde una lista sólo escribía
                         * el texto: no ejecutaba PagoService::aplicar(), así que
                         * el dinero entraba y no se acreditaba a ninguna
                         * exhibición. El contrato lo seguía reclamando.
                         *
                         * Esos dos estatus los pone el servicio, con el botón
                         * "Aplicar al contrato" o con "Reversar".
                         */
                        Select::make('estatus')
                            ->label('Situación del pago')
                            ->options([
                                'registrado' => 'Registrado',
                                'confirmado' => 'Confirmado',
                                'rechazado'  => 'Rechazado',
                            ])
                            ->default('registrado')
                            ->required()
                            ->live()
                            // Un pago ya aplicado o reversado muestra su estatus
                            // real, pero no se deja cambiar a mano.
                            ->disabled(fn (?Pago $record) => in_array($record?->estatus, ['aplicado', 'reversado'], true))
                            ->dehydrated()
                            ->helperText(fn (?Pago $record) => match ($record?->estatus) {
                                'aplicado'  => 'Ya está aplicado: el sistema lo repartió entre las exhibiciones.',
                                'reversado' => 'Fue reversado; su estatus ya no se modifica.',
                                default     => 'Para acreditarlo al contrato usa el botón «Aplicar al contrato».',
                            }),

                        Textarea::make('motivo_reverso')
                            ->label('Motivo del reverso')
                            ->rows(2)
                            ->required(fn (Get $get) => in_array($get('estatus'), ['reversado', 'rechazado'], true))
                            ->visible(fn (Get $get) => in_array($get('estatus'), ['reversado', 'rechazado'], true))
                            ->columnSpanFull(),

                        Textarea::make('notas')
                            ->label('Notas')
                            ->rows(2)
                            ->columnSpanFull(),

                    /**
                     * `monto_aplicado` y `saldo_a_favor` ya no se capturan: los
                     * calcula PagoService::aplicar() al repartir el pago. Editarlos
                     * a mano descuadraba la contabilidad del contrato.
                     *
                     * `registrado_por` y `confirmado_por` tampoco: se toman del
                     * usuario en sesión, no se teclea el número de usuario.
                     */
                    ]),
            ]),
        ]);
    }
}
