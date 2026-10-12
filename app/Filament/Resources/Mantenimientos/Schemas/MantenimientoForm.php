<?php

namespace App\Filament\Resources\Mantenimientos\Schemas;

use App\Models\Proveedor;
use App\Models\TipoMantenimiento;
use App\Models\Unidad;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Alta de servicio.
 *
 * Mismo criterio que el asistente de contrato: los catálogos se pueden dar
 * de alta sin salir del formulario. Aquí importa especialmente el tipo de
 * servicio — el negocio inventa tipos nuevos todo el tiempo (pulido,
 * polarizado, GPS) y no existe otra pantalla para crearlos.
 */
class MantenimientoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            /**
             * Mismo acomodo que el formulario de cotización: pestañas
             * arriba en vez de secciones apiladas, para que el alta no
             * sea un formulario largo por el que hay que bajar.
             */
            Tabs::make('Servicio')->columnSpanFull()->tabs([

                Tab::make('El servicio')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->schema([
                    Section::make('¿Quién paga este servicio?')
                        ->description('Define si esto es sólo bitácora del vehículo o un gasto de la arrendadora')
                        ->schema([
                            Radio::make('responsable_costo')
                                ->label('')
                                ->options([
                                    'arrendatario' => 'El cliente',
                                    'arrendadora'  => 'La arrendadora',
                                    'seguro'       => 'Lo cubre el seguro',
                                    'garantia'     => 'Va por garantía',
                                ])
                                ->default('arrendatario')
                                ->inline()
                                ->required()
                                ->live()
                                ->helperText('Por contrato, los gastos de mantenimiento corren por cuenta del cliente.')
                                ->columnSpanFull(),

                            /**
                             * Distingue los dos escenarios del cliente:
                             * paga directo en el taller, o la arrendadora adelanta.
                             */
                            Toggle::make('se_recobra')
                                ->label('La arrendadora lo pagó y se le cobrará al cliente')
                                ->default(false)
                                ->live()
                                ->helperText('Genera automáticamente el cargo en el calendario del contrato vigente, con vencimiento a 15 días.')
                                ->visible(fn (Get $get) => $get('responsable_costo') === 'arrendatario')
                                ->columnSpanFull(),
                        ])
                        ->columns(1),

                    Section::make('¿Qué se hizo y a qué vehículo?')
                        ->schema([
                            Select::make('unidad_id')
                                ->label('Vehículo')
                                ->options(fn () => Unidad::query()
                                    ->with('submarca.marca', 'color')
                                    ->get()
                                    ->mapWithKeys(fn ($u) => [$u->id => "{$u->codigo_interno} — {$u->descripcion}"]))
                                ->searchable()
                                ->required()
                                ->live()
                                // Precarga el odómetro que trae registrado la unidad.
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if ($u = Unidad::find($state)) {
                                        $set('kilometraje', $u->kilometraje_actual);
                                    }
                                })
                                ->columnSpan(2),

                            /**
                             * Alta al vuelo del tipo de servicio.
                             *
                             * Es la única vía para crear tipos: no hay recurso propio
                             * en el panel. Sin esto el módulo entero era inusable,
                             * porque `tipo_mantenimiento_id` no acepta nulo.
                             */
                            Select::make('tipo_mantenimiento_id')
                                ->label('Tipo de servicio')
                                ->options(fn () => TipoMantenimiento::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id'))
                                ->searchable()
                                ->required()
                                ->helperText('¿No está en la lista? Créalo con el +')
                                ->createOptionForm([
                                    TextInput::make('nombre')
                                        ->label('Nombre del servicio')
                                        ->placeholder('Servicio mayor, Cambio de llantas, Polarizado…')
                                        ->required()
                                        ->live(onBlur: true)
                                        // La clave se deriva del nombre: nadie debería inventarla.
                                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
                                            'clave',
                                            str($state ?? '')->slug('_')->toString()
                                        )),

                                    TextInput::make('clave')
                                        ->label('Clave')
                                        ->required()
                                        ->helperText('Se genera del nombre; sirve para identificarlo en reportes'),

                                    Radio::make('categoria')
                                        ->label('Categoría')
                                        ->options([
                                            'preventivo'  => 'Preventivo',
                                            'correctivo'  => 'Correctivo',
                                            'obligatorio' => 'Obligatorio (verificación, tenencia)',
                                        ])
                                        ->default('preventivo')
                                        ->required()
                                        ->columnSpanFull(),

                                    TextInput::make('periodicidad_meses')
                                        ->label('Se repite cada')
                                        ->numeric()->suffix('meses')
                                        ->helperText('Opcional, para calcular el próximo'),

                                    TextInput::make('periodicidad_km')
                                        ->label('O cada')
                                        ->numeric()->suffix('km'),
                                ])
                                ->createOptionUsing(fn (array $data) => TipoMantenimiento::firstOrCreate(
                                    ['clave' => $data['clave']],
                                    [
                                        'nombre'             => trim($data['nombre']),
                                        'categoria'          => $data['categoria'],
                                        'periodicidad_meses' => $data['periodicidad_meses'] ?? null,
                                        'periodicidad_km'    => $data['periodicidad_km'] ?? null,
                                        'genera_alerta'      => true,
                                        'activo'             => true,
                                    ],
                                )->id)
                                ->createOptionModalHeading('Nuevo tipo de servicio'),

                            Select::make('proveedor_id')
                                ->label('Taller')
                                ->options(fn () => Proveedor::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id'))
                                ->searchable()
                                ->helperText('¿Taller nuevo? Dalo de alta con el +')
                                ->createOptionForm([
                                    TextInput::make('nombre')->label('Nombre del taller')->required(),
                                    TextInput::make('telefono')->label('Teléfono')->tel(),
                                    TextInput::make('contacto_nombre')->label('Persona de contacto'),
                                    TextInput::make('rfc')->label('RFC')->maxLength(13),
                                ])
                                // Se registra como taller; si además vende autos, se
                                // marca después en su ficha.
                                ->createOptionUsing(fn (array $data) => Proveedor::create([
                                    'nombre'          => trim($data['nombre']),
                                    'telefono'        => $data['telefono'] ?? null,
                                    'contacto_nombre' => $data['contacto_nombre'] ?? null,
                                    'rfc'             => filled($data['rfc'] ?? null) ? strtoupper($data['rfc']) : null,
                                    'tipos'           => ['taller'],
                                    'activo'          => true,
                                ])->id)
                                ->createOptionModalHeading('Nuevo taller'),

                            DatePicker::make('fecha')
                                ->label('Fecha del servicio')
                                ->default(now())
                                ->maxDate(now())
                                ->required(),

                            TextInput::make('kilometraje')
                                ->label('Kilometraje')
                                ->numeric()
                                ->suffix('km')
                                ->helperText('Se toma del vehículo; ajústalo si difiere'),

                            TextInput::make('folio_taller')
                                ->label('Folio del taller'),

                            Select::make('estatus')
                                ->label('Situación')
                                ->options([
                                    'programado' => 'Programado',
                                    'en_proceso' => 'En proceso',
                                    'completado' => 'Completado',
                                    'cancelado'  => 'Cancelado',
                                ])
                                ->default('completado')
                                ->required(),
                        ])
                        ->columns(3),

                    ]),

                Tab::make('Costo')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                    Section::make('Costo')
                        ->schema([
                            TextInput::make('costo')
                                ->label('Costo sin IVA')
                                ->numeric()->prefix('$')->default(0)->required()
                                ->minValue(0)
                                ->live(onBlur: true)
                                // El IVA se propone solo; se puede corregir.
                                ->afterStateUpdated(fn (Get $get, Set $set) => $set(
                                    'costo_iva',
                                    round((float) $get('costo') * 0.16, 2)
                                )),

                            TextInput::make('costo_iva')
                                ->label('IVA')
                                ->numeric()->prefix('$')->default(0)->required()
                                ->minValue(0)
                                ->live(onBlur: true),

                            // Igual que el resumen del asistente de contrato.
                            Placeholder::make('costo_total')
                                ->label('Costo total')
                                ->content(fn (Get $get) => new HtmlString(
                                    "<span style='font-size:18px;font-weight:600'>$"
                                    . number_format((float) $get('costo') + (float) $get('costo_iva'), 2)
                                    . '</span>'
                                )),
                        ])
                        ->columns(3),

                    ]),

                Tab::make('Seguimiento')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                    Section::make('Próximo servicio')
                        ->description('Para que el sistema avise cuando toque')
                        ->schema([
                            DatePicker::make('proximo_fecha')
                                ->label('Próxima fecha'),

                            TextInput::make('proximo_kilometraje')
                                ->label('O a los')
                                ->numeric()
                                ->suffix('km'),
                        ])
                        ->columns(2),

                    Section::make('Detalle')
                        ->schema([
                            Textarea::make('descripcion')
                                ->label('¿Qué se hizo?')
                                ->rows(2)
                                ->columnSpanFull(),

                            Textarea::make('refacciones')
                                ->label('Refacciones')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(1)
                        ->collapsed(),
                    ]),
            ]),
        ]);
    }
}
