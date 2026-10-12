<?php

namespace App\Filament\Resources\PolizaSeguros\Schemas;

use App\Models\Aseguradora;
use App\Models\Unidad;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class PolizaSeguroForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            /**
             * Acomodo en pestañas, igual que el formulario de
             * cotización: el alta no debería ser un formulario largo
             * por el que hay que ir bajando.
             */
            Tabs::make('Póliza')->columnSpanFull()->tabs([

                Tab::make('Qué se asegura')
                    ->icon('heroicon-o-truck')
                    ->columns(2)
                    ->schema([
                        /**
                         * Antes el vehículo se elegía por su número de id. Ahora
                         * se ve el código interno y la descripción del auto.
                         */
                        Select::make('unidad_id')
                            ->label('Vehículo')
                            ->options(fn () => Unidad::query()
                                ->with('submarca.marca', 'color')
                                ->get()
                                ->mapWithKeys(fn ($u) => [$u->id => "{$u->codigo_interno} — {$u->descripcion}"]))
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),

                        Select::make('aseguradora_id')
                            ->label('Aseguradora')
                            ->options(fn () => Aseguradora::query()
                                ->where('activo', true)
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id'))
                            ->searchable()
                            ->required()
                            // Poder darla de alta sin salir del formulario.
                            ->createOptionForm([
                                TextInput::make('nombre')->label('Aseguradora')->required(),
                                TextInput::make('telefono_siniestros')
                                    ->label('Teléfono de siniestros')
                                    ->tel()
                                    ->helperText('El que marca el conductor tras un choque'),
                            ])
                            ->createOptionUsing(fn (array $data) => Aseguradora::firstOrCreate(
                                ['nombre' => trim($data['nombre'])],
                                ['telefono_siniestros' => $data['telefono_siniestros'] ?? null, 'activo' => true],
                            )->id)
                            ->createOptionModalHeading('Nueva aseguradora'),

                        TextInput::make('numero_poliza')
                            ->label('Número de póliza')
                            ->required(),
                    ]),

                Tab::make('Cobertura')
                    ->icon('heroicon-o-shield-check')
                    ->columns(3)
                    ->schema([
                        Select::make('tipo_cobertura')
                            ->label('Tipo de cobertura')
                            ->options([
                                'amplia'   => 'Amplia',
                                'limitada' => 'Limitada',
                                'rc'       => 'Responsabilidad civil',
                            ])
                            ->default('amplia')
                            ->required(),

                        TextInput::make('prima_total')
                            ->label('Prima total')
                            ->numeric()->prefix('$')->required()->minValue(0),

                        TextInput::make('suma_asegurada')
                            ->label('Suma asegurada')
                            ->numeric()->prefix('$')->minValue(0),

                        TextInput::make('deducible_danos')
                            ->label('Deducible de daños')
                            ->numeric()->step('0.0001')
                            ->helperText('Como fracción: 0.05 = 5%'),

                        TextInput::make('deducible_robo')
                            ->label('Deducible de robo')
                            ->numeric()->step('0.0001')
                            ->helperText('Como fracción: 0.10 = 10%'),
                    ]),

                Tab::make('Vigencia y pago')
                    ->icon('heroicon-o-calendar-days')
                    ->columns(4)
                    ->schema([
                        DatePicker::make('vigencia_inicio')
                            ->label('Vigente desde')
                            ->default(now())
                            ->required(),

                        DatePicker::make('vigencia_fin')
                            ->label('Vigente hasta')
                            ->default(now()->addYear())
                            ->required()
                            ->after('vigencia_inicio'),

                        Select::make('forma_pago')
                            ->label('Forma de pago')
                            ->options([
                                'contado'    => 'De contado',
                                'semestral'  => 'Semestral',
                                'trimestral' => 'Trimestral',
                                'mensual'    => 'Mensual',
                            ])
                            ->default('contado')
                            ->required(),

                        Select::make('estatus')
                            ->label('Situación')
                            ->options([
                                'vigente'    => 'Vigente',
                                'por_vencer' => 'Por vencer',
                                'vencida'    => 'Vencida',
                                'cancelada'  => 'Cancelada',
                            ])
                            ->default('vigente')
                            ->required(),

                        TextInput::make('conductor_designado')
                            ->label('Conductor designado')
                            ->columnSpanFull(),

                        Textarea::make('notas')
                            ->label('Notas')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]),
        ]);
    }
}
