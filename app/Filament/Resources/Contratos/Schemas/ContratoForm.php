<?php

namespace App\Filament\Resources\Contratos\Schemas;

use App\Models\Arrendatario;
use App\Models\Contrato;
use App\Models\Unidad;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Filament\Forms\CargosAlCliente;
use Filament\Schemas\Schema;


class ContratoForm
{
  public static function configure(Schema $schema): Schema
{
    return $schema->components([
        Tabs::make('Contrato')->columnSpanFull()->tabs([

            Tab::make('Partes')->schema([
                TextInput::make('folio')
                    ->label('Folio')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Se genera automáticamente'),

                Select::make('estatus')
                    ->options([
                        'borrador' => 'Borrador',
                        'por_firmar' => 'Por firmar',
                    ])
                    ->default('borrador')
                    ->disabled(fn (?Contrato $record) => $record && $record->estaVigente())
                    ->helperText('El estatus avanza solo al activar el contrato'),

                Select::make('unidad_id')
                    ->label('Unidad')
                    ->options(fn (?Contrato $record) => Unidad::query()
                        ->when($record, fn ($q) => $q->orWhere('id', $record->unidad_id))
                        ->whereIn('estatus', ['disponible', 'apartada'])
                        ->with('submarca.marca')
                        ->get()
                        ->mapWithKeys(fn ($u) => [$u->id => "{$u->codigo_interno} — {$u->descripcion}"]))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state) {
                        if ($u = Unidad::find($state)) {
                            $set('kilometraje_inicial', $u->kilometraje_actual);
                            $set('valor_residual_pactado', $u->valor_residual_estimado);
                        }
                    }),

                Select::make('arrendatario_id')
                    ->label('Arrendatario')
                    ->options(fn () => Arrendatario::activos()->get()
                        ->mapWithKeys(fn ($a) => [$a->id => "{$a->codigo} — {$a->nombre_completo}"]))
                    ->searchable()
                    ->required()
                    ->helperText('Sólo aparecen arrendatarios en estatus activo'),
            ])->columns(2),

            Tab::make('Vigencia y renta')->schema([
                DatePicker::make('fecha_firma')->label('Fecha de firma')->default(now()),

                DatePicker::make('fecha_inicio')
                    ->label('Inicio')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::calcularFin($get, $set)),

                TextInput::make('plazo_meses')
                    ->label('Plazo (meses)')
                    ->numeric()
                    ->default(48)
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::calcularFin($get, $set)),

                DatePicker::make('fecha_fin')
                    ->label('Fin')
                    ->required()
                    ->disabled()
                    ->dehydrated()
                    ->helperText('Se calcula del inicio más el plazo'),

                TextInput::make('renta_sin_iva')
                    ->label('Renta sin IVA')
                    ->numeric()->prefix('$')->minValue(0)->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::calcularIva($get, $set)),

                TextInput::make('tasa_iva')
                    ->label('Tasa de IVA')
                    ->numeric()->default(0.16)->step(0.01)->required()
                    ->helperText('0.16 = 16%')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::calcularIva($get, $set)),

                TextInput::make('renta_con_iva')
                    ->label('Renta con IVA')
                    ->numeric()->prefix('$')->minValue(0)->required()
                    ->disabled()->dehydrated(),

                Select::make('periodicidad')
                    ->options(['mensual' => 'Mensual', 'quincenal' => 'Quincenal', 'semanal' => 'Semanal'])
                    ->default('mensual')->required(),

                TextInput::make('dia_corte')
                    ->label('Día de corte')
                    ->numeric()->minValue(1)->maxValue(31)->default(1)
                    ->helperText('Día del mes en que vence cada renta'),
            ])->columns(2),

            Tab::make('Cargos al cliente')->schema([
                CargosAlCliente::seccion()->columnSpanFull(),
            ])->columns(1),

            Tab::make('Depósito y penalización')->schema([
                TextInput::make('deposito_garantia')->label('Depósito en garantía')
                    ->numeric()->prefix('$')->minValue(0)->default(0),

                TextInput::make('enganche')->numeric()->prefix('$')->minValue(0)->default(0),

                TextInput::make('otros_cargos_iniciales')->label('Otros cargos iniciales')
                    ->numeric()->prefix('$')->minValue(0)->default(0)->live(),

                TextInput::make('concepto_otros_cargos')->label('Concepto de otros cargos')
                    ->visible(fn (Get $get) => (float) $get('otros_cargos_iniciales') > 0),

                TextInput::make('dias_gracia')->label('Días de gracia')
                    ->numeric()->default(0)
                    ->helperText('Días después del vencimiento antes de generar mora'),

                Select::make('tipo_penalizacion')
                    ->label('Tipo de penalización')
                    ->options([
                        'ninguna' => 'Ninguna',
                        'porcentaje_mensual' => 'Porcentaje mensual sobre saldo',
                        'monto_fijo_diario' => 'Monto fijo por día',
                        'porcentaje_unico' => 'Porcentaje único',
                    ])
                    ->default('porcentaje_mensual')->required()->live(),

                TextInput::make('valor_penalizacion')
                    ->label(fn (Get $get) => match ($get('tipo_penalizacion')) {
                        'monto_fijo_diario' => 'Pesos por día',
                        'porcentaje_mensual' => 'Fracción mensual (0.05 = 5%)',
                        'porcentaje_unico' => 'Fracción única (0.10 = 10%)',
                        default => 'Valor',
                    })
                    ->numeric()->step(0.01)->default(0)
                    ->visible(fn (Get $get) => $get('tipo_penalizacion') !== 'ninguna'),

                TextInput::make('tope_penalizacion')->label('Tope de penalización')
                    ->numeric()->prefix('$')->minValue(0)
                    ->visible(fn (Get $get) => $get('tipo_penalizacion') !== 'ninguna'),
            ])->columns(2),

            Tab::make('Kilometraje y salida')->schema([
                TextInput::make('kilometraje_inicial')->label('Km inicial')->numeric(),
                TextInput::make('limite_km_anual')->label('Límite km anual')->numeric(),
                TextInput::make('costo_km_excedente')->label('Costo por km excedente')
                    ->numeric()->prefix('$')->minValue(0),

                Toggle::make('con_opcion_compra')->label('Con opción de compra')->live(),

                TextInput::make('valor_residual_pactado')
                    ->label('Valor residual pactado')
                    ->numeric()->prefix('$')->minValue(0)
                    ->visible(fn (Get $get) => $get('con_opcion_compra')),

                Textarea::make('clausulas_especiales')->label('Cláusulas especiales')
                    ->rows(3)->columnSpanFull(),

                Textarea::make('notas')->rows(2)->columnSpanFull(),
            ])->columns(2),
        ]),
    ]);
}

protected static function calcularFin(Get $get, Set $set): void
{
    $inicio = $get('fecha_inicio');
    $plazo  = (int) $get('plazo_meses');

    if ($inicio && $plazo > 0) {
        $set('fecha_fin', Carbon::parse($inicio)->addMonthsNoOverflow($plazo)->toDateString());
    }
}

protected static function calcularIva(Get $get, Set $set): void
{
    $base = (float) $get('renta_sin_iva');
    $tasa = (float) $get('tasa_iva');

    $set('renta_con_iva', round($base * (1 + $tasa), 2));
}
}
