<?php

namespace App\Filament\Resources\Cotizacions\Schemas;

use App\Models\Arrendatario;
use App\Models\Cotizacion;
use App\Filament\Forms\CargosAlCliente;
use App\Models\Color;
use App\Models\Marca;
use App\Models\Submarca;
use App\Models\Unidad;
use App\Services\CotizacionService;
use Filament\Forms\Components\DatePicker;
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
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class CotizacionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Cotización')->columnSpanFull()->tabs([

                Tab::make('Qué se cotiza')->schema([
                    Radio::make('proposito')
                        ->label('Propósito')
                        ->options([
                            'analisis_compra' => 'Análisis de compra (interno)',
                            'comercial'       => 'Propuesta a cliente',
                        ])
                        ->default('analisis_compra')
                        ->inline()->live()->required()->columnSpanFull(),

                    Radio::make('origen_vehiculo')
                        ->label('Vehículo')
                        ->options([
                            'existente'   => 'Unidad de mi flota',
                            'hipotetico'  => 'Auto que aún no compro',
                        ])
                        ->default('hipotetico')
                        ->inline()->live()->dehydrated(false)
                        ->afterStateHydrated(fn(Set $set, ?Cotizacion $record) =>
                        $set('origen_vehiculo', $record?->unidad_id ? 'existente' : 'hipotetico'))
                        ->columnSpanFull(),

                    Select::make('unidad_id')
                        ->label('Unidad')
                        ->options(fn() => Unidad::with('submarca.marca')->get()
                            ->mapWithKeys(fn($u) => [$u->id => "{$u->codigo_interno} — {$u->descripcion}"]))
                        ->searchable()
                        ->visible(fn(Get $get) => $get('origen_vehiculo') === 'existente')
                        ->live()
                        ->afterStateUpdated(function (Set $set, $state) {
                            if ($u = Unidad::find($state)) {
                                $set('precio_lista', $u->precio_lista);
                                $set('precio_adquisicion', $u->precio_adquisicion);
                                // Los gastos de la unidad NO se copian: placas y
                                // gestoría los paga el cliente y se cobran en
                                // "Cargos al cliente", no son inversión propia.
                                $set('valor_residual_estimado', $u->valor_residual_estimado);
                            }
                        }),

                    /**
                     * Marca primero, modelo después.
                     *
                     * No es columna de `cotizaciones`: sólo sirve para acotar
                     * la lista de modelos, por eso va con dehydrated(false).
                     * Al reabrir una cotización guardada se deduce del modelo
                     * que ya tiene, si no el select saldría vacío y el de
                     * modelo se vería sin opciones.
                     */
                    Select::make('marca_id')
                        ->label('Marca')
                        ->options(fn() => Marca::query()
                            ->where('activo', true)
                            ->orderBy('nombre')
                            ->pluck('nombre', 'id'))
                        ->searchable()
                        ->live()
                        ->dehydrated(false)
                        ->afterStateHydrated(fn(Set $set, ?Cotizacion $record) =>
                            $set('marca_id', $record?->submarca?->marca_id))
                        // Cambiar de marca invalida el modelo elegido.
                        ->afterStateUpdated(fn(Set $set) => $set('submarca_id', null))
                        ->visible(fn(Get $get) => $get('origen_vehiculo') === 'hipotetico'),

                    Select::make('submarca_id')
                        ->label('Modelo')
                        // Sólo los modelos de la marca elegida.
                        ->options(fn(Get $get) => filled($get('marca_id'))
                            ? Submarca::query()
                                ->where('marca_id', $get('marca_id'))
                                ->where('activo', true)
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id')
                            : [])
                        ->searchable()
                        ->disabled(fn(Get $get) => blank($get('marca_id')))
                        // disabled() no se guarda por defecto; se fuerza.
                        ->dehydrated()
                        ->helperText(fn(Get $get) => blank($get('marca_id'))
                            ? 'Elige primero una marca'
                            : null)
                        ->visible(fn(Get $get) => $get('origen_vehiculo') === 'hipotetico'),

                    TextInput::make('anio')->label('Año')->numeric()
                        ->visible(fn(Get $get) => $get('origen_vehiculo') === 'hipotetico'),

                    TextInput::make('version')->label('Versión')
                        ->visible(fn(Get $get) => $get('origen_vehiculo') === 'hipotetico'),

                    /**
                     * Color del catálogo.
                     *
                     * `cotizaciones.color_descripcion` es una columna de texto
                     * (no hay color_id), así que se guarda el NOMBRE del color
                     * y por eso las opciones van llaveadas por nombre. Los
                     * valores que ya estaban capturados a mano siguen siendo
                     * válidos porque el tipo de dato no cambia.
                     *
                     * El orden es por id, que en el catálogo va de más a menos
                     * vendido en México: blanco y grises primero.
                     */
                    Select::make('color_descripcion')
                        ->label('Color')
                        ->options(fn() => Color::query()->orderBy('id')->pluck('nombre', 'nombre'))
                        ->searchable()
                        ->createOptionForm([
                            TextInput::make('nombre')->label('Color')->required(),
                            TextInput::make('hex')->label('Hex')->maxLength(7)
                                ->placeholder('#C0C0C0')
                                ->helperText('Opcional, para el chip de color'),
                        ])
                        // Devuelve el nombre, no el id: es lo que se guarda.
                        ->createOptionUsing(fn(array $data) => Color::firstOrCreate(
                            ['nombre' => trim($data['nombre'])],
                            ['hex' => $data['hex'] ?? null],
                        )->nombre)
                        ->createOptionModalHeading('Nuevo color')
                        ->visible(fn(Get $get) => $get('origen_vehiculo') === 'hipotetico'),
                ])->columns(2),

                Tab::make('Cliente')
                    ->visible(fn(Get $get) => $get('proposito') === 'comercial')
                    ->schema([
                        Select::make('arrendatario_id')
                            ->label('Arrendatario registrado')
                            ->options(fn() => Arrendatario::all()
                                ->mapWithKeys(fn($a) => [$a->id => "{$a->codigo} — {$a->nombre_completo}"]))
                            ->searchable()
                            ->helperText('Déjalo vacío si aún es sólo un prospecto')
                            ->columnSpanFull(),

                        TextInput::make('prospecto_nombre')->label('Nombre del prospecto'),
                        TextInput::make('prospecto_empresa')->label('Empresa'),
                        TextInput::make('prospecto_email')->label('Correo')->email(),
                        TextInput::make('prospecto_telefono')->label('Teléfono')->tel(),

                        DatePicker::make('vigencia_hasta')
                            ->label('La oferta vence el')
                            ->default(now()->addDays(15)),
                    ])->columns(2),

                Tab::make('Números')->schema([
                    Section::make('Inversión')->schema([
                        TextInput::make('precio_lista')->label('Precio de lista')
                            ->numeric()->prefix('$')->minValue(0),

                        TextInput::make('precio_adquisicion')->label('Precio de compra')
                            ->numeric()->prefix('$')->minValue(0)->required()->live(onBlur: true)
                            // Es el denominador de TODOS los rendimientos.
                            ->helperText('Sobre este precio se mide el rendimiento')
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcularRenta($get, $set)),

                        /**
                         * Aquí ya NO se capturan placas ni gestoría.
                         *
                         * Ese gasto lo paga el cliente y se cobra en
                         * "Cargos al cliente" como `gastos_iniciales`.
                         * Capturarlo también aquí lo contaba dos veces:
                         * inflaba la inversión (bajando el rendimiento) y
                         * además se le cobraba al cliente.
                         */
                    ])->columns(2),

                    /**
                     * El flujo va al revés que antes: el usuario dice qué
                     * rendimiento quiere y el sistema despeja la renta.
                     * Sólo con criterio "manual" vuelve a capturarla a mano.
                     */
                    Section::make('¿Qué rendimiento buscas?')
                        ->description('Captura el objetivo y el sistema calcula cuánto cobrar')
                        ->schema([
                            Radio::make('criterio_rendimiento')
                                ->label('Medir el rendimiento como')
                                ->options([
                                    'anual'  => 'Rendimiento anual',
                                    'total'  => 'Rendimiento total del contrato',
                                    'renta'  => 'Rendimiento sólo por rentas (sin contar la venta final)',
                                    'manual' => 'Fijar la renta yo mismo',
                                ])
                                ->default('anual')
                                ->required()
                                ->live()
                                // Campo de trabajo: no existe como columna.
                                ->dehydrated(false)
                                // Una cotización guardada ya tiene su renta
                                // fijada; se abre en "manual" en lugar de
                                // suponer un objetivo que nadie capturó.
                                ->afterStateHydrated(fn(Set $set, ?Cotizacion $record) =>
                                    $set('criterio_rendimiento', $record?->exists ? 'manual' : 'anual'))
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcularRenta($get, $set))
                                ->columnSpanFull(),

                            TextInput::make('rendimiento_objetivo')
                                ->label('Objetivo')
                                ->numeric()
                                ->suffix('%')
                                ->default(30)
                                ->live(onBlur: true)
                                ->dehydrated(false)
                                // Se captura en entero (30) y se divide entre 100 al despejar.
                                ->helperText('En porcentaje: 30 = 30%')
                                ->visible(fn (Get $get) => $get('criterio_rendimiento') !== 'manual')
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcularRenta($get, $set)),
                        ])->columns(2),

                    Section::make('Términos')->schema([
                        // Necesarias para prorratear el primer mes y para
                        // imprimirlo en el documento comercial.
                        DatePicker::make('fecha_inicio_estimada')
                            ->label('Inicio estimado')
                            ->default(now()->addDay())
                            ->live(),

                        TextInput::make('dia_corte')
                            ->label('Día de corte')
                            ->numeric()->minValue(1)->maxValue(31)->default(1)
                            ->live(onBlur: true),

                        TextInput::make('plazo_meses')->label('Plazo (meses)')
                            ->numeric()->default(48)->required()->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcularRenta($get, $set)),

                        TextInput::make('renta_sin_iva')->label('Renta sin IVA')
                            ->numeric()->prefix('$')->minValue(0)->required()->live(onBlur: true)
                            ->disabled(fn (Get $get) => $get('criterio_rendimiento') !== 'manual')
                            // disabled() no se guarda por defecto; se fuerza.
                            ->dehydrated()
                            ->afterStateUpdated(fn(Get $get, Set $set) =>
                            $set('renta_con_iva', round((float) $get('renta_sin_iva') * (1 + (float) $get('tasa_iva')), 2))),

                        TextInput::make('tasa_iva')->numeric()->default(0.16)->step(0.01)->required(),

                        TextInput::make('renta_con_iva')->label('Renta con IVA')
                            ->numeric()->prefix('$')->minValue(0)->required()->live(onBlur: true)
                            ->disabled(fn (Get $get) => $get('criterio_rendimiento') !== 'manual')
                            ->dehydrated()
                            ->helperText(fn (Get $get) => $get('criterio_rendimiento') === 'manual'
                                ? null
                                : 'Calculada por el sistema, redondeada hacia arriba a la centena'),

                        TextInput::make('valor_residual_estimado')->label('Venta estimada al final')
                            ->numeric()->prefix('$')->minValue(0)->default(0)->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcularRenta($get, $set)),

                        TextInput::make('deposito_garantia')->label('Depósito')
                            ->numeric()->prefix('$')->minValue(0)->default(0),

                        TextInput::make('enganche')->numeric()->prefix('$')->minValue(0)->default(0),

                        TextInput::make('limite_km_anual')->label('Límite km/año')->numeric(),

                        Toggle::make('con_opcion_compra')->label('Con opción de compra'),
                    ])->columns(3),

                    /**
                     * Cargos al cliente. Ya se guardan: son los que imprime
                     * el documento comercial y los que se copian tal cual al
                     * convertir la cotización en contrato.
                     */
                    CargosAlCliente::seccion()->columnSpanFull(),

                    /**
                     * Se eliminó "Costos estimados" (seguro, mantenimiento,
                     * otros costos y días ociosos).
                     *
                     * Con este modelo de negocio el cliente absorbe el
                     * seguro, la gestoría y el mantenimiento, así que
                     * pronosticarlos como gasto propio distorsionaba el
                     * rendimiento en contra de la arrendadora. El
                     * mantenimiento real se registra en su propio módulo,
                     * no se predice aquí.
                     */

                    Section::make('Resultado')->schema([
                        Placeholder::make('preview')
                            ->label('')
                            ->content(fn(Get $get) => new HtmlString(self::preview($get)))
                            ->columnSpanFull(),
                    ]),
                ]),

                Tab::make('Seguimiento')->schema([
                    Select::make('estatus')->options([
                        'borrador' => 'Borrador',
                        'enviada' => 'Enviada',
                        'aceptada' => 'Aceptada',
                        'rechazada' => 'Rechazada',
                        'vencida' => 'Vencida',
                    ])->default('borrador')->required()->live(),

                    DatePicker::make('fecha_cotizacion')->label('Fecha')->default(now())->required(),
                    DatePicker::make('fecha_respuesta')->label('Fecha de respuesta'),

                    Textarea::make('motivo_rechazo')->rows(2)->columnSpanFull()
                        ->visible(fn(Get $get) => $get('estatus') === 'rechazada'),

                    Textarea::make('condiciones')->label('Condiciones especiales')->rows(3)->columnSpanFull(),
                    Textarea::make('notas')->rows(2)->columnSpanFull(),
                ])->columns(2),
            ]),
        ]);
    }

    /**
     * Despeja la renta a partir del objetivo y la escribe en el formulario.
     *
     * No hace nada con criterio "manual": ahí manda el usuario.
     * Si el despeje no es viable deja las rentas en cero; el Placeholder
     * de abajo se encarga de explicar por qué.
     */
    protected static function recalcularRenta(Get $get, Set $set): void
    {
        $criterio = $get('criterio_rendimiento') ?? 'anual';

        if ($criterio === 'manual') {
            return;
        }

        $r = app(CotizacionService::class)->rentaParaRendimiento(
            precioAdquisicion: (float) $get('precio_adquisicion'),
            criterio: $criterio,
            // El campo se captura en entero (30); el servicio espera fracción.
            objetivo: (float) $get('rendimiento_objetivo') / 100,
            plazoMeses: (int) $get('plazo_meses'),
            valorVentaUnidad: (float) $get('valor_residual_estimado'),
        );

        $set('renta_con_iva', $r['renta_con_iva']);
        $set('renta_sin_iva', $r['renta_sin_iva']);
    }

    /**
     * Resultado del despeje, con las fórmulas del negocio.
     * Denominador: precio_adquisicion (no la inversión con gastos).
     */
    protected static function resumenObjetivo(Get $get): array
    {
        // El criterio se normaliza UNA vez: al reabrir una cotización
        // guardada llega en null, y antes se colaba tal cual al servicio.
        $criterio = $get('criterio_rendimiento') ?: 'anual';

        return app(CotizacionService::class)->rentaParaRendimiento(
            precioAdquisicion: (float) $get('precio_adquisicion'),
            criterio: $criterio === 'manual' ? 'anual' : $criterio,
            objetivo: (float) $get('rendimiento_objetivo') / 100,
            plazoMeses: (int) $get('plazo_meses'),
            valorVentaUnidad: (float) $get('valor_residual_estimado'),
        );
    }

    /** Cálculo en vivo, sin guardar. Usa la misma fórmula del servicio. */
    protected static function preview(Get $get): string
    {
        $criterio = $get('criterio_rendimiento') ?? 'anual';

        // Si el objetivo pedido no se puede alcanzar, eso es LO ÚNICO que
        // importa mostrar: las cifras de abajo estarían en cero y confunden.
        if ($criterio !== 'manual') {
            $obj = self::resumenObjetivo($get);

            if (! $obj['viable']) {
                $motivo = e($obj['motivo'] ?? 'No se pudo calcular la renta.');

                return "<p style='color:#dc2626;font-size:14px;font-weight:600;line-height:1.7'>"
                    . "&#9888; {$motivo}</p>";
            }
        }

        $inversion = (float) $get('precio_adquisicion') + (float) $get('gastos_adquisicion');
        $plazo     = (int) $get('plazo_meses');
        $renta     = (float) $get('renta_con_iva');

        if ($inversion <= 0 || $plazo <= 0 || $renta <= 0) {
            return '<p class="text-sm text-gray-500">Captura inversión, plazo y renta para ver el resultado.</p>';
        }

        /**
         * Bloque del objetivo: usa las fórmulas del negocio, con
         * precio_adquisicion como denominador.
         *
         * Va aparte del bloque de abajo a propósito: aquél mide el
         * rendimiento NETO (descuenta seguro, mantenimiento y ociosidad)
         * sobre la inversión CON gastos, así que da un número distinto.
         * Son dos preguntas diferentes y se muestran como tales.
         */
        $precioCompra = (float) $get('precio_adquisicion');
        $ventaFinal   = (float) $get('valor_residual_estimado');
        $rentaTotalB  = $renta * $plazo;
        $ingresoB     = $rentaTotalB + $ventaFinal;
        $rendRentaB   = $precioCompra > 0 ? ($rentaTotalB / $precioCompra) - 1 : 0;
        $rendTotalB   = $precioCompra > 0 ? ($ingresoB / $precioCompra) - 1 : 0;
        $rendAnualB   = $plazo > 0 ? $rendTotalB / ($plazo / 12) : 0;

        $anios        = $plazo / 12;
        $rentaTotal   = $renta * $plazo;
        $residual     = (float) $get('valor_residual_estimado');
        $ingresoBruto = $rentaTotal + $residual;

        /**
         * Sin costos estimados: el cliente absorbe seguro, gestoría y
         * mantenimiento, así que la arrendadora no tiene egresos que
         * proyectar. La ganancia es el ingreso menos lo que costó el auto.
         */
        $ganancia = $ingresoBruto - $inversion;

        $rendTotal = ($ingresoBruto - $inversion) / $inversion;
        $rendAnual = $rendTotal / $anios;

        // Cuántos meses de renta se necesitan para recuperar la inversión.
        $equilibrio = $renta > 0 ? round($inversion / $renta, 1) : null;

        $color = $rendAnual >= 0.25 ? '#059669' : ($rendAnual >= 0.15 ? '#d97706' : '#dc2626');
        $f = fn($n) => '$' . number_format($n, 2);
        $p = fn($n) => number_format($n * 100, 2) . '%';

        return <<<HTML
    <div style="border:1px solid rgba(184,145,47,.35);padding:14px;margin-bottom:16px">
        <div style="font-size:11px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:#b8912f;margin-bottom:10px">
            Rendimiento sobre el precio de compra ({$f($precioCompra)})
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;font-size:14px">
            <div><div style="color:#6b7280;font-size:12px">Renta mensual con IVA</div><div style="font-weight:700;font-size:18px">{$f($renta)}</div></div>
            <div><div style="color:#6b7280;font-size:12px">Renta total del plazo</div><div style="font-weight:600">{$f($rentaTotalB)}</div></div>
            <div><div style="color:#6b7280;font-size:12px">Ingreso bruto</div><div style="font-weight:600">{$f($ingresoB)}</div></div>
            <div><div style="color:#6b7280;font-size:12px">Rend. sólo rentas</div><div style="font-weight:600">{$p($rendRentaB)}</div></div>
            <div><div style="color:#6b7280;font-size:12px">Rend. total</div><div style="font-weight:600">{$p($rendTotalB)}</div></div>
            <div><div style="color:#6b7280;font-size:12px">Rend. anual</div><div style="font-weight:700;font-size:18px;color:#059669">{$p($rendAnualB)}</div></div>
        </div>
    </div>

    <div style="font-size:11px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:#6b7280;margin-bottom:8px">
        Recuperación de la inversión ({$f($inversion)})
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;font-size:14px">
        <div><div style="color:#6b7280;font-size:12px">Inversión</div><div style="font-weight:600">{$f($inversion)}</div></div>
        <div><div style="color:#6b7280;font-size:12px">Renta total del plazo</div><div style="font-weight:600">{$f($rentaTotal)}</div></div>
        <div><div style="color:#6b7280;font-size:12px">Ingreso bruto</div><div style="font-weight:600">{$f($ingresoBruto)}</div></div>
        <div><div style="color:#6b7280;font-size:12px">Ganancia</div><div style="font-weight:600">{$f($ganancia)}</div></div>
        <div><div style="color:#6b7280;font-size:12px">Rendimiento total</div><div style="font-weight:600">{$p($rendTotal)}</div></div>
        <div><div style="color:#6b7280;font-size:12px">Rendimiento anual</div><div style="font-weight:700;font-size:18px;color:{$color}">{$p($rendAnual)}</div></div>
        <div><div style="color:#6b7280;font-size:12px">Recuperas la inversión en</div><div style="font-weight:600">{$equilibrio} meses de renta</div></div>
    </div>
    HTML;
    }
}
