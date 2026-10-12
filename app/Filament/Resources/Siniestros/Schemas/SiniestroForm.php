<?php

namespace App\Filament\Resources\Siniestros\Schemas;

use App\Models\Arrendatario;
use App\Models\Contrato;
use App\Models\PolizaSeguro;
use App\Models\Unidad;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SiniestroForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            /**
             * Acomodo en pestañas, igual que el formulario de
             * cotización: el alta no debería ser un formulario largo
             * por el que hay que ir bajando.
             */
            Tabs::make('Siniestro')->columnSpanFull()->tabs([

                Tab::make('El vehículo')
                    ->icon('heroicon-o-truck')
                    ->columns(3)
                    ->schema([
                        /**
                         * El vehículo es la llave de todo lo demás: al elegirlo
                         * se deducen su póliza vigente, su contrato y su cliente,
                         * en vez de pedir cuatro números de id sueltos.
                         */
                        Select::make('unidad_id')
                            ->label('Vehículo')
                            ->options(fn () => Unidad::query()
                                ->with('submarca.marca', 'color')
                                ->get()
                                ->mapWithKeys(fn ($u) => [$u->id => "{$u->codigo_interno} — {$u->descripcion}"]))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if (! $u = Unidad::find($state)) {
                                    return;
                                }

                                $set('poliza_seguro_id', $u->polizaVigente()?->id);

                                if ($c = $u->contratoVigente()) {
                                    $set('contrato_id', $c->id);
                                    $set('arrendatario_id', $c->arrendatario_id);
                                }
                            })
                            ->columnSpanFull(),

                        Select::make('poliza_seguro_id')
                            ->label('Póliza')
                            ->options(fn (Get $get) => PolizaSeguro::query()
                                ->with('aseguradora')
                                ->when($get('unidad_id'), fn ($q) => $q->where('unidad_id', $get('unidad_id')))
                                ->get()
                                ->mapWithKeys(fn ($p) => [
                                    $p->id => "{$p->aseguradora?->nombre} — {$p->numero_poliza}",
                                ]))
                            ->searchable()
                            ->helperText('Se toma la vigente del vehículo; cámbiala si aplica otra'),

                        Select::make('contrato_id')
                            ->label('Contrato')
                            ->options(fn () => Contrato::query()
                                ->with('arrendatario')
                                ->get()
                                ->mapWithKeys(fn ($c) => [
                                    $c->id => "{$c->folio} — {$c->arrendatario?->nombre_completo}",
                                ]))
                            ->searchable()
                            ->helperText('Si el auto estaba arrendado al ocurrir'),

                        Select::make('arrendatario_id')
                            ->label('Cliente')
                            ->options(fn () => Arrendatario::query()
                                ->get()
                                ->mapWithKeys(fn ($a) => [$a->id => "{$a->codigo} — {$a->nombre_completo}"]))
                            ->searchable(),
                    ]),

                Tab::make('El hecho')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->columns(3)
                    ->schema([
                        Select::make('tipo')
                            ->label('Tipo de siniestro')
                            ->options([
                                'colision'       => 'Colisión',
                                'robo_total'     => 'Robo total',
                                'robo_parcial'   => 'Robo parcial',
                                'cristales'      => 'Cristales',
                                'daños_terceros' => 'Daños a terceros',
                                'otro'           => 'Otro',
                            ])
                            ->required(),

                        DateTimePicker::make('fecha_siniestro')
                            ->label('Fecha y hora')
                            ->default(now())
                            ->maxDate(now())
                            ->required(),

                        TextInput::make('numero_reporte')
                            ->label('Número de reporte')
                            ->helperText('El que da la aseguradora'),

                        TextInput::make('lugar')
                            ->label('Lugar')
                            ->columnSpanFull(),

                        Textarea::make('descripcion')
                            ->label('Cómo ocurrió')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Tab::make('Dinero y trámite')
                    ->icon('heroicon-o-banknotes')
                    ->columns(3)
                    ->schema([
                        TextInput::make('monto_estimado')
                            ->label('Daño estimado')
                            ->numeric()->prefix('$')->minValue(0),

                        TextInput::make('deducible_pagado')
                            ->label('Deducible pagado')
                            ->numeric()->prefix('$')->minValue(0)
                            ->live(onBlur: true),

                        Select::make('responsable_deducible')
                            ->label('¿Quién pagó el deducible?')
                            ->options([
                                'arrendadora'  => 'La arrendadora',
                                'arrendatario' => 'El cliente',
                                'tercero'      => 'Un tercero',
                            ])
                            // Sólo tiene sentido preguntarlo si hubo deducible.
                            ->required(fn (Get $get) => (float) $get('deducible_pagado') > 0),

                        TextInput::make('monto_recuperado')
                            ->label('Recuperado de la aseguradora')
                            ->numeric()->prefix('$')->minValue(0),

                        Select::make('estatus')
                            ->label('Situación del trámite')
                            ->options([
                                'reportado'     => 'Reportado',
                                'en_valuacion'  => 'En valuación',
                                'en_reparacion' => 'En reparación',
                                'cerrado'       => 'Cerrado',
                                'perdida_total' => 'Pérdida total',
                            ])
                            ->default('reportado')
                            ->required()
                            ->live(),

                        DatePicker::make('fecha_cierre')
                            ->label('Fecha de cierre')
                            ->visible(fn (Get $get) => in_array($get('estatus'), ['cerrado', 'perdida_total'], true))
                            ->required(fn (Get $get) => in_array($get('estatus'), ['cerrado', 'perdida_total'], true)),

                        Textarea::make('notas')
                            ->label('Notas')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]),
        ]);
    }
}
