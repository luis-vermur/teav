<?php

namespace App\Filament\Resources\Contratos\Pages;

use App\Filament\Forms\CampoRfc;
use App\Filament\Forms\CargosAlCliente;
use App\Filament\Resources\Contratos\ContratoResource;
use App\Models\Arrendatario;
use App\Models\Marca;
use App\Models\Submarca;
use App\Models\TipoDocumento;
use App\Models\Unidad;
use App\Services\ContratoService;
use App\Services\CotizacionService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use App\Models\Aseguradora;
use App\Models\Color;
use App\Models\PolizaSeguro;
use App\Models\Proveedor;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;

/**
 * Asistente de contrato.
 *
 * Todo el flujo en una sola pantalla: cliente (nuevo o existente),
 * vehículo, términos y documentos. Al terminar puede activar el
 * contrato sin que el usuario tenga que ir a otro módulo.
 */
class CreateContrato extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ContratoResource::class;

    /** Se llena en el paso 4 y se usa después de guardar. */
    public bool $activarAlGuardar = false;

    protected function getSteps(): array
    {
        return [

            // ─────────────── 1. CLIENTE ───────────────
            Step::make('Cliente')
                ->description('¿Quién va a rentar?')
                ->icon('heroicon-o-user')
                ->schema([
                    Select::make('arrendatario_id')
                        ->label('Busca al cliente por nombre o RFC')
                        ->options(fn () => Arrendatario::query()
                            ->whereIn('estatus', ['activo', 'prospecto', 'en_validacion'])
                            ->get()
                            ->mapWithKeys(fn ($a) => [
                                $a->id => "{$a->nombre_completo} · {$a->rfc}",
                            ]))
                        ->searchable()
                        ->required()
                        ->live()
                        ->columnSpanFull()
                        ->helperText('Si el cliente es nuevo, usa el botón + para darlo de alta sin salir de aquí')

                        // ── Alta de cliente sin salir del asistente ──
                        ->createOptionForm([
                            Radio::make('tipo_persona')
                                ->label('Tipo de persona')
                                ->options(['fisica' => 'Persona física', 'moral' => 'Empresa'])
                                ->default('fisica')
                                ->inline()
                                ->live()
                                ->required()
                                ->columnSpanFull(),

                            TextInput::make('nombre')->label('Nombre(s)')
                                ->visible(fn (Get $get) => $get('tipo_persona') === 'fisica')
                                ->required(fn (Get $get) => $get('tipo_persona') === 'fisica'),

                            TextInput::make('apellido_paterno')->label('Apellido paterno')
                                ->visible(fn (Get $get) => $get('tipo_persona') === 'fisica')
                                ->required(fn (Get $get) => $get('tipo_persona') === 'fisica'),

                            TextInput::make('apellido_materno')->label('Apellido materno')
                                ->visible(fn (Get $get) => $get('tipo_persona') === 'fisica'),

                            TextInput::make('razon_social')->label('Razón social')
                                ->visible(fn (Get $get) => $get('tipo_persona') === 'moral')
                                ->required(fn (Get $get) => $get('tipo_persona') === 'moral')
                                ->columnSpanFull(),

                            TextInput::make('representante_legal_nombre')->label('Representante legal')
                                ->visible(fn (Get $get) => $get('tipo_persona') === 'moral'),

                            CampoRfc::make()->required(),

                            TextInput::make('telefono')->label('Teléfono')->tel()->required(),
                            TextInput::make('email')->label('Correo')->email(),
                        ])
                        ->createOptionUsing(function (array $data) {
                            $data['estatus'] = 'prospecto';
                            $data['registrado_por'] = auth()->id();

                            return Arrendatario::create($data)->id;
                        })
                        ->createOptionModalHeading('Alta rápida de cliente'),

                    Placeholder::make('ficha_cliente')
                        ->label('')
                        ->content(function (Get $get) {
                            $a = Arrendatario::find($get('arrendatario_id'));

                            if (! $a) {
                                return new HtmlString(
                                    "<p style='color:#6b7280;font-size:14px'>Selecciona un cliente para ver su situación.</p>"
                                );
                            }

                            $faltantes = $a->documentosFaltantes();
                            $adeudo    = $a->saldoVencido();

                            $lineas = ["<b>{$a->nombre_completo}</b> · {$a->codigo}"];

                            if ($adeudo > 0) {
                                $lineas[] = "<span style='color:#dc2626'>⚠ Debe $"
                                    . number_format($adeudo, 2) . " en otro contrato</span>";
                            }

                            if ($faltantes) {
                                $lineas[] = "<span style='color:#d97706'>Faltan documentos: "
                                    . implode(', ', $faltantes) . " (los subes en el paso 4)</span>";
                            } else {
                                $lineas[] = "<span style='color:#059669'>✓ Expediente completo</span>";
                            }

                            return new HtmlString(
                                "<div style='font-size:14px;line-height:1.9'>" . implode('<br>', $lineas) . "</div>"
                            );
                        })
                        ->columnSpanFull(),
                ])
                ->columns(1),

            // ─────────────── 2. VEHÍCULO ───────────────
            Step::make('Vehículo')
                ->description('¿Qué auto se renta?')
                ->icon('heroicon-o-truck')
                ->schema([
                    Select::make('unidad_id')
                        ->label('Vehículo disponible')
                        ->options(fn () => Unidad::query()
                            ->whereIn('estatus', ['disponible', 'apartada'])
                            ->with('submarca.marca', 'color')
                            ->get()
                            ->mapWithKeys(fn ($u) => [
                                $u->id => "{$u->codigo_interno} · {$u->descripcion}",
                            ]))
                        ->searchable()
                        ->required()
                        ->live()
                        ->columnSpanFull()
                        ->helperText('Sólo aparecen los vehículos libres. Si el auto es nuevo, dalo de alta con el botón + sin salir del asistente')
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            if ($u = Unidad::find($state)) {
                                $set('kilometraje_inicial', $u->kilometraje_actual);
                                $set('valor_residual_pactado', $u->valor_residual_estimado);

                                // El seguro que se le recobra al cliente es la
                                // prima de la póliza vigente de esa unidad.
                                if ($poliza = $u->polizaVigente()) {
                                    $set('monto_seguro_anual', (float) $poliza->prima_total);
                                }

                                // Cambiar de coche cambia el precio base del
                                // despeje, así que la renta se recalcula.
                                self::recalcularRentaObjetivo($get, $set);
                            }
                        })

                        // ── Alta de vehículo (y su póliza) sin salir del asistente ──
                        // Mismo patrón que el cliente del paso 1. Al crear, Filament
                        // fija el state del select y dispara afterStateUpdated, así que
                        // la ficha de abajo se refresca sola con la unidad nueva.
                        ->createOptionForm(self::formularioAltaVehiculo())
                        ->createOptionUsing(fn (array $data) => self::crearUnidadConPoliza($data))
                        ->createOptionModalHeading('Alta rápida de vehículo')
                        ->createOptionAction(fn (Action $action) => $action->modalWidth(Width::ThreeExtraLarge)),

                    Placeholder::make('ficha_unidad')
                        ->label('')
                        ->content(function (Get $get) {
                            $u = Unidad::find($get('unidad_id'));

                            if (! $u) {
                                return new HtmlString(
                                    "<p style='color:#6b7280;font-size:14px'>Selecciona un vehículo.</p>"
                                );
                            }

                            $poliza = $u->polizaVigente();

                            $lineas = [
                                "<b>{$u->descripcion}</b> · VIN {$u->vin}",
                                "Kilometraje: " . number_format($u->kilometraje_actual) . " km",
                                "Inversión: $" . number_format($u->inversion_total, 2),
                            ];

                            $lineas[] = $poliza
                                ? "<span style='color:#059669'>✓ Asegurado hasta "
                                    . $poliza->vigencia_fin->format('d/M/Y') . "</span>"
                                : "<span style='color:#dc2626'>⚠ Sin póliza vigente — no se podrá activar el contrato</span>";

                            return new HtmlString(
                                "<div style='font-size:14px;line-height:1.9'>" . implode('<br>', $lineas) . "</div>"
                            );
                        })
                        ->columnSpanFull(),
                ])
                ->columns(1),

            // ─────────────── 3. TÉRMINOS ───────────────
            Step::make('Términos')
                ->description('Renta, plazo y condiciones')
                ->icon('heroicon-o-currency-dollar')
                ->schema([
                    DatePicker::make('fecha_inicio')
                        ->label('¿Cuándo empieza?')
                        ->default(now()->addDay()->startOfDay())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::calcularFin($get, $set)),

                    TextInput::make('plazo_meses')
                        ->label('¿Por cuántos meses?')
                        ->numeric()->default(48)->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            self::calcularFin($get, $set);
                            self::recalcularRentaObjetivo($get, $set);
                        }),

                    DatePicker::make('fecha_fin')
                        ->label('Termina el')
                        ->required()->disabled()->dehydrated(),

                    TextInput::make('dia_corte')
                        ->label('Día de pago cada mes')
                        ->numeric()->minValue(1)->maxValue(31)->default(1)
                        ->helperText('Ej. 5 = vence el día 5 de cada mes'),

                    /**
                     * Se captura el rendimiento deseado y el sistema despeja
                     * la renta. El precio sale de la unidad del paso 2.
                     */
                    Radio::make('criterio_rendimiento')
                        ->label('¿Qué rendimiento buscas?')
                        ->options([
                            'anual'  => 'Rendimiento anual',
                            'total'  => 'Rendimiento total del contrato',
                            'renta'  => 'Rendimiento sólo por rentas (sin contar la venta final)',
                            'manual' => 'Fijar la renta yo mismo',
                        ])
                        ->default('anual')
                        ->required()
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcularRentaObjetivo($get, $set))
                        ->columnSpanFull(),

                    TextInput::make('rendimiento_objetivo')
                        ->label('Objetivo')
                        ->numeric()->suffix('%')->default(30)
                        ->helperText('En porcentaje: 30 = 30%')
                        ->live(onBlur: true)
                        ->dehydrated(false)
                        ->visible(fn (Get $get) => $get('criterio_rendimiento') !== 'manual')
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcularRentaObjetivo($get, $set)),

                    TextInput::make('renta_sin_iva')
                        ->label('Renta mensual sin IVA')
                        ->numeric()->prefix('$')->minValue(0)->required()
                        ->live(onBlur: true)
                        ->disabled(fn (Get $get) => $get('criterio_rendimiento') !== 'manual')
                        ->dehydrated()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::calcularIva($get, $set)),

                    TextInput::make('tasa_iva')
                        ->label('IVA')->numeric()->default(0.16)->step(0.01)->required()
                        ->helperText('0.16 = 16%')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::calcularIva($get, $set)),

                    TextInput::make('renta_con_iva')
                        ->label('Cobras cada mes')
                        ->numeric()->prefix('$')->minValue(0)->required()
                        ->disabled()->dehydrated()
                        ->helperText(fn (Get $get) => $get('criterio_rendimiento') === 'manual'
                            ? null
                            : 'Calculada, redondeada hacia arriba a la centena'),

                    TextInput::make('deposito_garantia')
                        ->label('Depósito en garantía')
                        ->numeric()->prefix('$')->minValue(0)->default(0),

                    TextInput::make('enganche')->numeric()->prefix('$')->minValue(0)->default(0),

                    TextInput::make('dias_gracia')
                        ->label('Días de tolerancia')
                        ->numeric()->default(5)
                        ->helperText('Antes de cobrar recargo'),

                    Select::make('tipo_penalizacion')
                        ->label('Recargo por atraso')
                        ->options([
                            'ninguna'            => 'Sin recargo',
                            'porcentaje_mensual' => 'Porcentaje mensual sobre lo vencido',
                            'monto_fijo_diario'  => 'Cantidad fija por día',
                            'porcentaje_unico'   => 'Porcentaje único',
                        ])
                        ->default('porcentaje_mensual')->required()->live(),

                    TextInput::make('valor_penalizacion')
                        ->label(fn (Get $get) => match ($get('tipo_penalizacion')) {
                            'monto_fijo_diario' => 'Pesos por día',
                            default             => 'Fracción (0.05 = 5%)',
                        })
                        ->numeric()->step(0.01)->default(0.05)
                        ->visible(fn (Get $get) => $get('tipo_penalizacion') !== 'ninguna'),

                    TextInput::make('limite_km_anual')
                        ->label('Límite de km al año')
                        ->numeric()->placeholder('Déjalo vacío si no hay límite'),

                    TextInput::make('costo_km_excedente')
                        ->label('Cobro por km extra')
                        ->numeric()->prefix('$')->minValue(0)
                        ->visible(fn (Get $get) => filled($get('limite_km_anual'))),

                    Toggle::make('con_opcion_compra')
                        ->label('El cliente puede comprar el auto al final')
                        ->live(),

                    TextInput::make('valor_residual_pactado')
                        ->label('Precio de compra al final')
                        ->numeric()->prefix('$')->minValue(0)
                        ->visible(fn (Get $get) => $get('con_opcion_compra')),

                    CargosAlCliente::seccion()->columnSpanFull(),

                    Placeholder::make('resumen_terminos')
                        ->label('')
                        ->content(function (Get $get) {
                            $renta = (float) $get('renta_con_iva');
                            $plazo = (int) $get('plazo_meses');

                            if ($renta <= 0 || $plazo <= 0) {
                                return new HtmlString(
                                    "<p style='color:#6b7280;font-size:14px'>Captura la renta y el plazo.</p>"
                                );
                            }

                            $total   = $renta * $plazo;
                            $inicial = (float) $get('deposito_garantia') + (float) $get('enganche');
                            $f       = fn ($n) => '$' . number_format($n, 2);
                            $f0      = fn ($n) => '$' . number_format($n, 0);

                            $bloques = [
                                "<div style='font-size:15px;line-height:2'>"
                                . "El cliente paga <b>{$f($inicial)}</b> al firmar<br>"
                                . "y <b>{$f($renta)}</b> cada mes durante <b>{$plazo}</b> meses.<br>"
                                . "<span style='color:#059669'>Total del contrato: <b>{$f($total)}</b></span>"
                                . "</div>",
                            ];

                            // De dónde salió la renta, dicho como lo diría una persona.
                            if ($d = self::despejeActual($get)) {
                                if (! $d['viable']) {
                                    $bloques[] = "<div style='margin-top:12px;color:#dc2626;font-size:14px;font-weight:600'>"
                                        . '&#9888; ' . e($d['motivo'] ?? '') . '</div>';
                                } else {
                                    $u    = $d['_unidad'];
                                    $obj  = (float) $get('rendimiento_objetivo');
                                    $como = match ($get('criterio_rendimiento')) {
                                        'total' => 'total del contrato',
                                        'renta' => 'sólo por rentas',
                                        default => 'anual',
                                    };

                                    $venta = (float) $u->valor_residual_estimado > 0
                                        ? ', considerando venderlo en <b>' . $f0($u->valor_residual_estimado) . '</b> al final'
                                        : '';

                                    $bloques[] = "<div style='margin-top:12px;padding-top:12px;border-top:1px solid rgba(184,145,47,.25);font-size:14px;line-height:1.9;color:#6b7280'>"
                                        . "Para obtener <b>" . rtrim(rtrim(number_format($obj, 2, '.', ''), '0'), '.') . "% {$como}</b> "
                                        . "sobre un coche de <b>{$f0($u->precio_adquisicion)}</b> "
                                        . "en <b>{$plazo} meses</b>{$venta}, "
                                        . "debes cobrar <b>{$f0($d['renta_con_iva'])}</b> mensuales con IVA."
                                        . '</div>';
                                }
                            }

                            return new HtmlString(implode('', $bloques));
                        })
                        ->columnSpanFull(),

                    Textarea::make('clausulas_especiales')
                        ->label('Condiciones especiales')
                        ->rows(2)->columnSpanFull(),
                ])
                ->columns(3),

            // ─────────────── 4. DOCUMENTOS Y CIERRE ───────────────
            Step::make('Documentos')
                ->description('Expediente y activación')
                ->icon('heroicon-o-document-check')
                ->schema([
                    Placeholder::make('checklist')
                        ->label('Estado del expediente')
                        ->content(function (Get $get) {
                            $a = Arrendatario::find($get('arrendatario_id'));

                            if (! $a) {
                                return new HtmlString('—');
                            }

                            $faltantes = $a->fresh()->documentosFaltantes();

                            if (! $faltantes) {
                                return new HtmlString(
                                    "<div style='color:#059669;font-size:14px'>✓ El cliente tiene toda su documentación. Puedes activar el contrato de una vez.</div>"
                                );
                            }

                            return new HtmlString(
                                "<div style='color:#d97706;font-size:14px'>Faltan: <b>"
                                . implode('</b>, <b>', $faltantes)
                                . "</b>.<br>Súbelos aquí abajo o después desde la ficha del cliente.</div>"
                            );
                        })
                        ->columnSpanFull(),

                    Repeater::make('documentos_cliente')
                        ->label('Subir documentos del cliente')
                        ->schema([
                            Select::make('tipo_documento_id')
                                ->label('Tipo')
                                ->options(fn () => TipoDocumento::query()
                                    ->where('aplica_a', Arrendatario::class)
                                    ->where('activo', true)
                                    ->orderBy('orden')
                                    ->pluck('nombre', 'id'))
                                ->required(),

                            FileUpload::make('archivo')
                                ->label('Archivo')
                                ->disk('documentos')
                                ->directory('arrendatarios/temporal')
                                ->visibility('private')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                ->maxSize(10240)
                                ->required(),

                            DatePicker::make('fecha_vencimiento')->label('Vence (opcional)'),
                        ])
                        ->columns(3)
                        ->addActionLabel('Agregar documento')
                        ->default([])
                        ->dehydrated(false)
                        ->columnSpanFull(),

                    Toggle::make('activar_ahora')
                        ->label('Activar el contrato al guardar')
                        ->helperText('Genera el calendario de pagos y marca el vehículo como arrendado. Si lo dejas apagado, queda en borrador.')
                        ->default(false)
                        ->dehydrated(false)
                        ->live()
                        ->columnSpanFull(),
                ])
                ->columns(1),
        ];
    }

    // ─────────────── Helpers ───────────────

    protected static function calcularFin(Get $get, Set $set): void
    {
        $inicio = $get('fecha_inicio');
        $plazo  = (int) $get('plazo_meses');

        if ($inicio && $plazo > 0) {
            $set('fecha_fin', Carbon::parse($inicio)->addMonthsNoOverflow($plazo)->toDateString());
        }
    }

    /**
     * Despeja la renta a partir del rendimiento buscado.
     *
     * El precio base sale de la unidad elegida en el paso 2: su columna
     * `precio_adquisicion`, NO el accesor `inversion_total` (ése suma los
     * gastos y daría un rendimiento distinto al que pide el negocio).
     * El valor de venta sale de `valor_residual_estimado` de la misma unidad.
     */
    protected static function recalcularRentaObjetivo(Get $get, Set $set): void
    {
        if (($get('criterio_rendimiento') ?? 'anual') === 'manual') {
            return;
        }

        $u = Unidad::find($get('unidad_id'));

        if (! $u) {
            return;
        }

        $r = app(CotizacionService::class)->rentaParaRendimiento(
            precioAdquisicion: (float) $u->precio_adquisicion,
            criterio: $get('criterio_rendimiento') ?? 'anual',
            objetivo: (float) $get('rendimiento_objetivo') / 100,
            plazoMeses: (int) $get('plazo_meses'),
            valorVentaUnidad: (float) $u->valor_residual_estimado,
        );

        $set('renta_con_iva', $r['renta_con_iva']);
        $set('renta_sin_iva', $r['renta_sin_iva']);
    }

    /** El mismo despeje, para que el resumen explique de dónde sale la renta. */
    protected static function despejeActual(Get $get): ?array
    {
        if (($get('criterio_rendimiento') ?? 'anual') === 'manual') {
            return null;
        }

        $u = Unidad::find($get('unidad_id'));

        if (! $u) {
            return null;
        }

        return app(CotizacionService::class)->rentaParaRendimiento(
            precioAdquisicion: (float) $u->precio_adquisicion,
            criterio: $get('criterio_rendimiento') ?? 'anual',
            objetivo: (float) $get('rendimiento_objetivo') / 100,
            plazoMeses: (int) $get('plazo_meses'),
            valorVentaUnidad: (float) $u->valor_residual_estimado,
        ) + ['_unidad' => $u];
    }

    protected static function calcularIva(Get $get, Set $set): void
    {
        $base = (float) $get('renta_sin_iva');
        $tasa = (float) $get('tasa_iva');

        $set('renta_con_iva', round($base * (1 + $tasa), 2));
    }

    // ─────────────── Ciclo de guardado ───────────────

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->activarAlGuardar = (bool) ($this->data['activar_ahora'] ?? false);

        $data['estatus']        = 'borrador';
        $data['periodicidad']   = 'mensual';
        $data['fecha_firma']    = now();
        $data['registrado_por'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->guardarDocumentos();

        if (! $this->activarAlGuardar) {
            Notification::make()
                ->title('Contrato guardado en borrador')
                ->body('Actívalo cuando esté firmado para generar el calendario de pagos.')
                ->success()
                ->send();

            return;
        }

        try {
            app(ContratoService::class)->activar($this->record);

            Notification::make()
                ->title('Contrato activado')
                ->body("Se generaron {$this->record->plazo_meses} mensualidades y el vehículo quedó arrendado.")
                ->success()
                ->send();
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('El contrato se guardó, pero no se pudo activar')
                ->body($e->getMessage())
                ->warning()
                ->persistent()
                ->send();
        }
    }

    /** Mueve los archivos del repeater al expediente del cliente. */
    protected function guardarDocumentos(): void
    {
        $docs = $this->data['documentos_cliente'] ?? [];

        if (! $docs) {
            return;
        }

        $arrendatario = $this->record->arrendatario;

        foreach ($docs as $doc) {
            $ruta = is_array($doc['archivo'] ?? null)
                ? reset($doc['archivo'])
                : ($doc['archivo'] ?? null);

            if (! $ruta || ! Storage::disk('documentos')->exists($ruta)) {
                continue;
            }

            $destino = 'arrendatarios/' . $arrendatario->id . '/' . basename($ruta);
            Storage::disk('documentos')->move($ruta, $destino);

            $arrendatario->documentos()->create([
                'tipo_documento_id' => $doc['tipo_documento_id'],
                'nombre_original'   => basename($ruta),
                'ruta'              => $destino,
                'disco'             => 'documentos',
                'mime'              => Storage::disk('documentos')->mimeType($destino),
                'tamano'            => Storage::disk('documentos')->size($destino),
                'fecha_vencimiento' => $doc['fecha_vencimiento'] ?? null,
                'subido_por'        => auth()->id(),
                'vigente'           => true,
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    // ═══════════════════════════════════════════════════════════════════
    //  ALTA RÁPIDA DE VEHÍCULO (paso 2)
    //
    //  Sólo lo indispensable para poder firmar un contrato: identificar la
    //  unidad, saber cuánto costó y si está asegurada. Todo lo demás
    //  (motor, transmisión, GPS, ubicación, notas) se completa después
    //  desde la ficha del vehículo. Si este modal creciera hasta ser tan
    //  largo como el formulario completo, dejaría de tener sentido.
    // ═══════════════════════════════════════════════════════════════════

    /** Segmentos válidos, mismos que usa el catálogo de submarcas. */
    private const SEGMENTOS = [
        'Sedán'     => 'Sedán',
        'SUV'       => 'SUV',
        'Hatchback' => 'Hatchback',
        'Pickup'    => 'Pickup',
        'Van'       => 'Van',
        'Deportivo' => 'Deportivo',
        'Coupé'     => 'Coupé',
    ];

    private static function formularioAltaVehiculo(): array
    {
        return [

            // ─────────────── VEHÍCULO ───────────────
            Section::make('Vehículo')
                ->description('Cómo se identifica la unidad')
                ->icon('heroicon-o-truck')
                ->schema([

                    Select::make('submarca_id')
                        ->label('Marca y modelo')
                        ->options(fn () => Submarca::query()
                            ->with('marca')
                            ->where('activo', true)
                            ->get()
                            ->mapWithKeys(fn ($s) => [$s->id => $s->nombre_completo])
                            ->sort())
                        ->searchable()
                        ->required()
                        ->columnSpan(2)
                        ->helperText('¿No está el modelo? Créalo con el +')

                        // Tercer nivel de modal: marca dentro de modelo dentro
                        // de vehículo. Es lo más profundo que conviene anidar.
                        ->createOptionForm([
                            Select::make('marca_id')
                                ->label('Marca')
                                ->options(fn () => Marca::query()
                                    ->where('activo', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id'))
                                ->searchable()
                                ->required()
                                ->createOptionForm([
                                    TextInput::make('nombre')->label('Marca')->required(),
                                    TextInput::make('pais_origen')->label('País de origen'),
                                ])
                                ->createOptionUsing(fn (array $data) => Marca::firstOrCreate(
                                    ['nombre' => trim($data['nombre'])],
                                    ['pais_origen' => $data['pais_origen'] ?? null, 'activo' => true],
                                )->id)
                                ->createOptionModalHeading('Nueva marca'),

                            TextInput::make('nombre')
                                ->label('Modelo')
                                ->required()
                                ->helperText('Sin la marca: "Versa", no "Nissan Versa"'),

                            Select::make('segmento')
                                ->label('Segmento')
                                ->options(self::SEGMENTOS)
                                ->required(),
                        ])
                        // firstOrCreate respeta el único compuesto (marca_id, nombre).
                        ->createOptionUsing(fn (array $data) => Submarca::firstOrCreate(
                            ['marca_id' => $data['marca_id'], 'nombre' => trim($data['nombre'])],
                            ['segmento' => $data['segmento'] ?? null, 'activo' => true],
                        )->id)
                        ->createOptionModalHeading('Nuevo modelo'),

                    TextInput::make('anio')
                        ->label('Año')
                        ->numeric()
                        ->required()
                        ->minValue(1990)
                        ->maxValue(now()->year + 2)
                        ->default(now()->year),

                    Select::make('color_id')
                        ->label('Color')
                        ->options(fn () => Color::query()->orderBy('id')->pluck('nombre', 'id'))
                        ->searchable()
                        ->createOptionForm([
                            TextInput::make('nombre')->label('Color')->required(),
                            TextInput::make('hex')
                                ->label('Hex')
                                ->maxLength(7)
                                ->placeholder('#C0C0C0')
                                ->helperText('Opcional, para el chip de color'),
                        ])
                        ->createOptionUsing(fn (array $data) => Color::firstOrCreate(
                            ['nombre' => trim($data['nombre'])],
                            ['hex' => $data['hex'] ?? null],
                        )->id)
                        ->createOptionModalHeading('Nuevo color'),

                    TextInput::make('version')
                        ->label('Versión')
                        ->placeholder('Advanced 35 TFSI')
                        ->helperText('Opcional'),

                    TextInput::make('vin')
                        ->label('VIN')
                        ->required()
                        ->length(17)
                        ->live(onBlur: true)
                        // Se normaliza a mayúsculas EN EL STATE, no sólo al guardar:
                        // así la validación de unicidad compara lo mismo que se inserta.
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('vin', strtoupper(trim($state ?? ''))))
                        ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                        // OJO: el índice único de `unidades` es de tabla, no filtrado,
                        // así que alcanza también a las unidades dadas de baja. La
                        // validación se hace contra TODAS las filas a propósito: si
                        // excluyera las soft-deleted, el VIN pasaría la validación y
                        // reventaría después con un error de constraint de Postgres.
                        ->rules(['unique:unidades,vin'])
                        ->validationMessages([
                            'unique' => 'Ese VIN ya existe. Puede pertenecer a un vehículo dado de baja: búscalo en Vehículos y restáuralo en lugar de capturarlo otra vez.',
                        ]),

                    TextInput::make('placas')
                        ->label('Placas')
                        ->maxLength(15)
                        ->helperText('Opcional, si aún no las tiene'),

                    TextInput::make('codigo_interno')
                        ->label('Código interno')
                        ->maxLength(255)
                        ->placeholder('Se genera solo')
                        ->helperText('Déjalo vacío y se asigna el siguiente TEAV-000')
                        ->rules(['unique:unidades,codigo_interno']),
                ])
                ->columns(2),

            // ─────────────── COMPRA ───────────────
            Section::make('Compra')
                ->description('Cuánto costó poner la unidad a rentar')
                ->icon('heroicon-o-banknotes')
                ->schema([

                    Select::make('proveedor_id')
                        ->label('Proveedor / agencia')
                        ->options(fn () => Proveedor::query()
                            ->where('activo', true)
                            ->orderBy('nombre')
                            ->pluck('nombre', 'id'))
                        ->searchable()
                        ->createOptionForm([
                            TextInput::make('nombre')->label('Nombre')->required(),
                            CampoRfc::make(),
                            TextInput::make('telefono')->label('Teléfono')->tel(),
                        ])
                        // tipos es json (cast a array). Se da de alta como agencia;
                        // si además es taller, se marca después en su ficha.
                        ->createOptionUsing(fn (array $data) => Proveedor::create([
                            'nombre'   => trim($data['nombre']),
                            'rfc'      => filled($data['rfc'] ?? null) ? strtoupper($data['rfc']) : null,
                            'telefono' => $data['telefono'] ?? null,
                            'tipos'    => ['agencia'],
                            'activo'   => true,
                        ])->id)
                        ->createOptionModalHeading('Nuevo proveedor'),

                    DatePicker::make('fecha_adquisicion')
                        ->label('Fecha de compra')
                        ->default(now())
                        ->maxDate(now()),

                    TextInput::make('factura_folio')
                        ->label('Folio de factura'),

                    TextInput::make('precio_lista')
                        ->label('Precio de lista')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0)
                        ->helperText('Antes de descuento'),

                    TextInput::make('precio_adquisicion')
                        ->label('Precio de compra')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0)
                        ->required()
                        ->live(onBlur: true),

                    TextInput::make('gastos_adquisicion')
                        ->label('Gastos de compra')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0)
                        ->default(0)
                        ->live(onBlur: true)
                        ->helperText('Placas, trámites, accesorios'),

                    TextInput::make('valor_residual_estimado')
                        ->label('Valor residual estimado')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0)
                        ->helperText('En cuánto esperas venderlo al final'),

                    TextInput::make('kilometraje_actual')
                        ->label('Kilometraje')
                        ->numeric()
                        ->default(0)
                        ->suffix('km'),

                    Placeholder::make('inversion_total_preview')
                        ->label('Inversión total')
                        ->content(function (Get $get) {
                            // Se reutiliza el accesor del modelo en lugar de repetir
                            // la fórmula aquí. La instancia no se guarda.
                            $u = new Unidad([
                                'precio_adquisicion' => (float) ($get('precio_adquisicion') ?: 0),
                                'gastos_adquisicion' => (float) ($get('gastos_adquisicion') ?: 0),
                            ]);

                            return new HtmlString(
                                "<span style='font-size:18px;font-weight:600'>$"
                                . number_format($u->inversion_total, 2)
                                . "</span><br><span style='color:#6b7280;font-size:13px'>precio de compra + gastos</span>"
                            );
                        })
                        ->columnSpanFull(),
                ])
                ->columns(2),

            // ─────────────── SEGURO ───────────────
            Section::make('Seguro')
                ->description('Sin póliza vigente el contrato no se podrá activar')
                ->icon('heroicon-o-shield-check')
                ->schema([

                    Toggle::make('capturar_poliza')
                        ->label('Capturar la póliza ahora')
                        ->default(true)
                        ->live()
                        ->columnSpanFull()
                        ->helperText('Apágalo si el seguro todavía no se contrata; lo capturas después en la ficha del vehículo'),

                    Placeholder::make('aviso_sin_poliza')
                        ->label('')
                        ->content(new HtmlString(
                            "<div style='color:#dc2626;font-size:14px'>⚠ Sin póliza vigente el contrato no se podrá activar.</div>"
                        ))
                        ->visible(fn (Get $get) => ! $get('capturar_poliza'))
                        ->columnSpanFull(),

                    Select::make('aseguradora_id')
                        ->label('Aseguradora')
                        ->options(fn () => Aseguradora::query()
                            ->where('activo', true)
                            ->orderBy('nombre')
                            ->pluck('nombre', 'id'))
                        ->searchable()
                        ->required(fn (Get $get) => (bool) $get('capturar_poliza'))
                        ->visible(fn (Get $get) => (bool) $get('capturar_poliza'))
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
                        ->required(fn (Get $get) => (bool) $get('capturar_poliza'))
                        ->visible(fn (Get $get) => (bool) $get('capturar_poliza')),

                    Select::make('tipo_cobertura')
                        ->label('Cobertura')
                        ->options([
                            'amplia'    => 'Amplia',
                            'limitada'  => 'Limitada',
                            'rc'        => 'Responsabilidad civil',
                        ])
                        ->default('amplia')
                        ->required(fn (Get $get) => (bool) $get('capturar_poliza'))
                        ->visible(fn (Get $get) => (bool) $get('capturar_poliza')),

                    DatePicker::make('vigencia_inicio')
                        ->label('Vigente desde')
                        ->default(now())
                        ->required(fn (Get $get) => (bool) $get('capturar_poliza'))
                        ->visible(fn (Get $get) => (bool) $get('capturar_poliza')),

                    DatePicker::make('vigencia_fin')
                        ->label('Vigente hasta')
                        ->default(now()->addYear())
                        ->required(fn (Get $get) => (bool) $get('capturar_poliza'))
                        ->visible(fn (Get $get) => (bool) $get('capturar_poliza'))
                        ->after('vigencia_inicio'),

                    TextInput::make('prima_total')
                        ->label('Prima total')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0)
                        ->required(fn (Get $get) => (bool) $get('capturar_poliza'))
                        ->visible(fn (Get $get) => (bool) $get('capturar_poliza')),

                    TextInput::make('suma_asegurada')
                        ->label('Suma asegurada')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0)
                        ->visible(fn (Get $get) => (bool) $get('capturar_poliza')),

                    TextInput::make('deducible_danos')
                        ->label('Deducible daños')
                        ->numeric()
                        ->step('0.0001')
                        ->visible(fn (Get $get) => (bool) $get('capturar_poliza'))
                        ->helperText('Como fracción: 0.05 = 5%'),

                    TextInput::make('deducible_robo')
                        ->label('Deducible robo')
                        ->numeric()
                        ->step('0.0001')
                        ->visible(fn (Get $get) => (bool) $get('capturar_poliza'))
                        ->helperText('Como fracción: 0.10 = 10%'),
                ])
                ->columns(2),
        ];
    }

    /**
     * Crea la unidad y, si se capturó, su póliza. Todo o nada.
     *
     * El orden NO es negociable: la póliza necesita unidad_id, así que la
     * unidad va primero y la póliza sólo se intenta si aquélla existe. Si
     * la póliza truena (número duplicado con la misma aseguradora, por el
     * único compuesto), la transacción revierte también la unidad y el
     * usuario no se queda con un vehículo a medio capturar.
     */
    private static function crearUnidadConPoliza(array $data): int
    {
        return DB::transaction(function () use ($data) {

            // 1. La unidad.
            $unidad = Unidad::create([
                'codigo_interno'          => filled($data['codigo_interno'] ?? null)
                    ? strtoupper(trim($data['codigo_interno']))
                    : self::siguienteCodigoInterno(),
                'vin'                     => strtoupper(trim($data['vin'])),
                'placas'                  => $data['placas'] ?? null,
                'submarca_id'             => $data['submarca_id'],
                'color_id'                => $data['color_id'] ?? null,
                'anio'                    => $data['anio'],
                'version'                 => $data['version'] ?? null,

                'fecha_adquisicion'       => $data['fecha_adquisicion'] ?? null,
                'proveedor_id'            => $data['proveedor_id'] ?? null,
                'factura_folio'           => $data['factura_folio'] ?? null,
                'precio_lista'            => $data['precio_lista'] ?? null,
                'precio_adquisicion'      => $data['precio_adquisicion'],
                'gastos_adquisicion'      => $data['gastos_adquisicion'] ?? 0,
                'valor_residual_estimado' => $data['valor_residual_estimado'] ?? null,

                // Recién ingresada: el odómetro de entrada es el actual.
                'kilometraje_actual'      => $data['kilometraje_actual'] ?? 0,
                'kilometraje_inicial'     => $data['kilometraje_actual'] ?? 0,

                'estatus'                 => 'disponible',
                'registrado_por'          => auth()->id(),
            ]);

            // 2. La póliza, sólo después de que la unidad existe.
            if ($data['capturar_poliza'] ?? false) {
                PolizaSeguro::create([
                    'unidad_id'       => $unidad->id,
                    'aseguradora_id'  => $data['aseguradora_id'],
                    'numero_poliza'   => trim($data['numero_poliza']),
                    'tipo_cobertura'  => $data['tipo_cobertura'],
                    'vigencia_inicio' => $data['vigencia_inicio'],
                    'vigencia_fin'    => $data['vigencia_fin'],
                    'prima_total'     => $data['prima_total'],
                    'suma_asegurada'  => $data['suma_asegurada'] ?? null,
                    'deducible_danos' => $data['deducible_danos'] ?? null,
                    'deducible_robo'  => $data['deducible_robo'] ?? null,
                    'estatus'         => 'vigente',
                ]);
            }

            // 3. El id que el Select va a adoptar como su nuevo valor.
            return $unidad->id;
        });
    }

    /**
     * Siguiente consecutivo TEAV-000.
     *
     * withTrashed a propósito: el índice único de `codigo_interno` no está
     * filtrado por deleted_at, así que una unidad dada de baja sigue
     * ocupando su código y hay que contarla para no chocar con ella.
     * Se ignoran los códigos que no siguen el patrón (capturados a mano).
     */
    private static function siguienteCodigoInterno(): string
    {
        $ultimo = Unidad::withTrashed()
            ->where('codigo_interno', 'like', 'TEAV-%')
            ->pluck('codigo_interno')
            ->map(fn ($codigo) => preg_match('/^TEAV-(\d+)$/', $codigo, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return sprintf('TEAV-%03d', $ultimo + 1);
    }
}
