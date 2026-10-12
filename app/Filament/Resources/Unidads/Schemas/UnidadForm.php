<?php

namespace App\Filament\Resources\Unidads\Schemas;

use App\Filament\Forms\CampoRfc;
use App\Models\Marca;
use App\Models\Submarca;
use App\Models\Unidad;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class UnidadForm
{
    public static function configure(Schema $schema): Schema
{
    return $schema->components([
        Tabs::make('Unidad')->columnSpanFull()->tabs([

            Tab::make('Identificación')->schema([
                TextInput::make('codigo_interno')
                    ->label('Código interno')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),

                TextInput::make('vin')
                    ->label('VIN')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->length(17)
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->dehydrateStateUsing(fn (?string $state) => strtoupper($state ?? '')),

                TextInput::make('placas')->maxLength(15),
                TextInput::make('numero_motor')->label('Número de motor'),
            ])->columns(2),

            Tab::make('Vehículo')->schema([
                Select::make('marca_id')
                    ->label('Marca')
                    ->options(fn () => Marca::orderBy('nombre')->pluck('nombre', 'id'))
                    ->searchable()
                    // Catálogo: se puede dar de alta sin salir del formulario.
                    ->createOptionForm([
                        TextInput::make('nombre')->label('Marca')->required(),
                        TextInput::make('pais_origen')->label('País de origen'),
                    ])
                    ->createOptionUsing(fn (array $data) => Marca::firstOrCreate(
                        ['nombre' => trim($data['nombre'])],
                        ['pais_origen' => $data['pais_origen'] ?? null, 'activo' => true],
                    )->id)
                    ->createOptionModalHeading('Nueva marca')
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('submarca_id', null))
                    ->dehydrated(false)     // no existe en la tabla, sólo filtra
                    ->afterStateHydrated(function (Set $set, ?Unidad $record) {
                        $set('marca_id', $record?->submarca?->marca_id);
                    }),

                Select::make('submarca_id')
                    ->label('Submarca')
                    ->options(fn (Get $get) => Submarca::where('marca_id', $get('marca_id'))
                        ->orderBy('nombre')->pluck('nombre', 'id'))
                    ->searchable()
                    // Hereda la marca ya elegida arriba: no se vuelve a pedir.
                    ->createOptionForm([
                        TextInput::make('nombre')
                            ->label('Modelo')
                            ->required()
                            ->helperText('Sin la marca: "Versa", no "Nissan Versa"'),
                        Select::make('segmento')
                            ->label('Segmento')
                            ->options([
                                'Sedán' => 'Sedán', 'SUV' => 'SUV', 'Hatchback' => 'Hatchback',
                                'Pickup' => 'Pickup', 'Van' => 'Van',
                                'Deportivo' => 'Deportivo', 'Coupé' => 'Coupé',
                            ])
                            ->required(),
                    ])
                    ->createOptionUsing(fn (array $data, Get $get) => Submarca::firstOrCreate(
                        ['marca_id' => $get('marca_id'), 'nombre' => trim($data['nombre'])],
                        ['segmento' => $data['segmento'] ?? null, 'activo' => true],
                    )->id)
                    ->createOptionModalHeading('Nuevo modelo')
                    ->disabled(fn (Get $get) => blank($get('marca_id')))
                    ->helperText(fn (Get $get) => blank($get('marca_id')) ? 'Elige primero una marca' : null)
                    ->required(),

                Select::make('color_id')
                    ->label('Color')
                    ->relationship('color', 'nombre')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('nombre')->label('Color')->required(),
                        TextInput::make('hex')->label('Hex')->maxLength(7)
                            ->placeholder('#C0C0C0')
                            ->helperText('Opcional, para el chip de color'),
                    ])
                    ->createOptionModalHeading('Nuevo color'),

                TextInput::make('anio')
                    ->label('Año')
                    ->numeric()
                    ->required()
                    ->minValue(1990)
                    ->maxValue((int) date('Y') + 2),

                TextInput::make('version')->label('Versión'),

                Select::make('transmision')->label('Transmisión')->options([
                    'manual' => 'Manual', 'automatica' => 'Automática',
                    'cvt' => 'CVT', 'dsg' => 'DSG',
                ]),

                Select::make('combustible')->options([
                    'gasolina' => 'Gasolina', 'diesel' => 'Diésel',
                    'hibrido' => 'Híbrido', 'electrico' => 'Eléctrico',
                ]),

                TextInput::make('numero_pasajeros')->label('Pasajeros')->numeric(),
            ])->columns(2),

            Tab::make('Adquisición')->schema([
                DatePicker::make('fecha_adquisicion')->label('Fecha de adquisición'),

                Select::make('proveedor_id')
                    ->label('Proveedor / agencia')
                    ->relationship('proveedor', 'nombre')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('nombre')->label('Nombre')->required(),
                        CampoRfc::make(),
                        TextInput::make('telefono')->label('Teléfono')->tel(),
                    ])
                    // Se da de alta como agencia; si además es taller, se
                    // marca después en su ficha.
                    ->createOptionUsing(fn (array $data) => \App\Models\Proveedor::create([
                        'nombre'   => trim($data['nombre']),
                        'rfc'      => filled($data['rfc'] ?? null) ? strtoupper($data['rfc']) : null,
                        'telefono' => $data['telefono'] ?? null,
                        'tipos'    => ['agencia'],
                        'activo'   => true,
                    ])->id)
                    ->createOptionModalHeading('Nuevo proveedor'),

                TextInput::make('factura_folio')->label('Folio de factura'),

                Select::make('forma_adquisicion')->label('Forma de adquisición')->options([
                    'contado' => 'Contado', 'credito' => 'Crédito',
                    'arrendamiento_puro' => 'Arrendamiento puro',
                ])->default('contado'),

                TextInput::make('precio_lista')
                    ->label('Precio de lista')
                    ->numeric()->prefix('$')->minValue(0),

                TextInput::make('precio_adquisicion')
                    ->label('Precio de adquisición')
                    ->numeric()->prefix('$')->minValue(0)->required()
                    ->helperText('Lo que realmente se pagó, ya con descuento'),

                TextInput::make('gastos_adquisicion')
                    ->label('Gastos de adquisición')
                    ->numeric()->prefix('$')->minValue(0)->default(0)
                    ->helperText('Placas, gestoría, traslado, accesorios'),

                TextInput::make('valor_residual_estimado')
                    ->label('Valor residual estimado')
                    ->numeric()->prefix('$')->minValue(0)
                    ->helperText('En cuánto esperas venderla al final'),
            ])->columns(2),

            Tab::make('Operación')->schema([
                Select::make('estatus')->options([
                    'en_transito' => 'En tránsito', 'disponible' => 'Disponible',
                    'apartada' => 'Apartada', 'arrendada' => 'Arrendada',
                    'mantenimiento' => 'En mantenimiento', 'siniestrada' => 'Siniestrada',
                    'en_venta' => 'En venta', 'vendida' => 'Vendida', 'baja' => 'Baja',
                ])->default('disponible')->required(),

                TextInput::make('ubicacion')->label('Ubicación'),
                TextInput::make('kilometraje_inicial')->numeric()->default(0),
                TextInput::make('kilometraje_actual')->numeric()->default(0),
                DatePicker::make('fecha_ultimo_kilometraje')->label('Último registro de km'),

                Toggle::make('tiene_gps')->label('Tiene GPS')->live(),
                TextInput::make('gps_identificador')
                    ->label('Identificador GPS')
                    ->visible(fn (Get $get) => $get('tiene_gps')),

                Textarea::make('notas')->columnSpanFull()->rows(3),
            ])->columns(2),
        ]),
    ]);
}
}
