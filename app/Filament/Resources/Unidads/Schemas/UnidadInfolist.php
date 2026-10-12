<?php

namespace App\Filament\Resources\Unidads\Schemas;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Models\Mantenimiento;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ficha del vehículo.
 *
 * Se apoya en los accesores del modelo (descripcion, inversion_total,
 * descuento_obtenido) en lugar de recalcular. Sin ids crudos: la submarca
 * se muestra con nombre_completo y el proveedor por su nombre.
 */
class UnidadInfolist
{
    /** Situación de la unidad: etiqueta legible y color semántico. */
    private const ESTATUS = [
        'en_transito'   => ['En tránsito', 'gray'],
        'disponible'    => ['Disponible', 'success'],
        'apartada'      => ['Apartada', 'warning'],
        'arrendada'     => ['Arrendada', 'info'],
        'mantenimiento' => ['En mantenimiento', 'warning'],
        'siniestrada'   => ['Siniestrada', 'danger'],
        'en_venta'      => ['En venta', 'warning'],
        'vendida'       => ['Vendida', 'gray'],
        'baja'          => ['Baja', 'danger'],
    ];

    private const TRANSMISION = [
        'manual' => 'Manual', 'automatica' => 'Automática', 'cvt' => 'CVT', 'dsg' => 'DSG',
    ];

    private const COMBUSTIBLE = [
        'gasolina' => 'Gasolina', 'diesel' => 'Diésel', 'hibrido' => 'Híbrido', 'electrico' => 'Eléctrico',
    ];

    private const COBERTURA = [
        'amplia' => 'Amplia', 'limitada' => 'Limitada', 'rc' => 'Responsabilidad civil',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // ─────────────── RESUMEN ───────────────
            Section::make()
                ->schema([
                    TextEntry::make('estatus')
                        ->label('Situación')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::ESTATUS[$state][0] ?? ucfirst((string) $state))
                        ->color(fn ($state) => self::ESTATUS[$state][1] ?? 'gray'),

                    TextEntry::make('kilometraje_actual')
                        ->label('Kilometraje')
                        ->formatStateUsing(fn ($state) => number_format((int) $state) . ' km'),

                    TextEntry::make('contrato_vigente')
                        ->label('Contrato vigente')
                        ->state(fn ($record) => $record->contratoVigente()?->folio)
                        ->placeholder('Sin contrato')
                        ->url(fn ($record) => ($c = $record->contratoVigente())
                            ? ContratoResource::getUrl('view', ['record' => $c])
                            : null)
                        ->color(fn ($record) => $record->contratoVigente() ? 'primary' : 'gray'),

                    TextEntry::make('dias_en_estatus')
                        ->label('Días en esta situación')
                        ->state(function ($record) {
                            // El tramo abierto del historial es el estatus actual.
                            $tramo = $record->historialEstatus()
                                ->whereNull('fin')
                                ->latest('inicio')
                                ->first();

                            return $tramo
                                ? $tramo->inicio->diffInDays(now()) . ' días'
                                : null;
                        })
                        ->placeholder('Sin historial'),
                ])
                ->columns(4),

            // ─────────────── IDENTIFICACIÓN ───────────────
            Section::make('Identificación')
                ->schema([
                    TextEntry::make('codigo_interno')
                        ->label('Código interno')
                        ->copyable()
                        ->weight('bold'),

                    TextEntry::make('vin')
                        ->label('VIN')
                        ->copyable(),

                    TextEntry::make('placas')
                        ->label('Placas')
                        ->copyable()
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('numero_motor')
                        ->label('Número de motor')
                        ->hidden(fn ($state) => blank($state)),

                    // nombre_completo del accesor de Submarca: "Marca Modelo".
                    TextEntry::make('submarca.nombre_completo')
                        ->label('Marca y modelo'),

                    TextEntry::make('anio')
                        ->label('Año'),

                    TextEntry::make('version')
                        ->label('Versión')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('color.nombre')
                        ->label('Color')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('transmision')
                        ->label('Transmisión')
                        ->hidden(fn ($state) => blank($state))
                        ->formatStateUsing(fn ($state) => self::TRANSMISION[$state] ?? $state),

                    TextEntry::make('combustible')
                        ->label('Combustible')
                        ->hidden(fn ($state) => blank($state))
                        ->formatStateUsing(fn ($state) => self::COMBUSTIBLE[$state] ?? $state),

                    TextEntry::make('numero_pasajeros')
                        ->label('Pasajeros')
                        ->hidden(fn ($state) => blank($state)),
                ])
                ->columns(4),

            // ─────────────── INVERSIÓN ───────────────
            Section::make('Inversión')
                ->schema([
                    TextEntry::make('precio_lista')
                        ->label('Precio de lista')
                        ->money('MXN')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('precio_adquisicion')
                        ->label('Precio de compra')
                        ->money('MXN'),

                    TextEntry::make('gastos_adquisicion')
                        ->label('Gastos adicionales')
                        ->money('MXN')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('inversion_total')
                        ->label('Inversión total')
                        ->money('MXN')
                        ->weight('bold'),

                    TextEntry::make('descuento_obtenido')
                        ->label('Descuento obtenido')
                        ->hidden(fn ($state) => blank($state) || $state <= 0)
                        ->color('success')
                        ->formatStateUsing(function ($state, $record) {
                            $pct = $record->precio_lista > 0
                                ? ($state / (float) $record->precio_lista) * 100
                                : 0;

                            return '$' . number_format($state, 2) . ' (' . number_format($pct, 1) . '%)';
                        }),

                    TextEntry::make('valor_residual_estimado')
                        ->label('Valor residual estimado')
                        ->money('MXN')
                        ->hidden(fn ($state) => blank($state)),
                ])
                ->columns(3),

            // ─────────────── SEGURO ───────────────
            Section::make('Seguro')
                ->schema([
                    // Sin póliza la ausencia ES la información: se grita en rojo.
                    TextEntry::make('sin_poliza')
                        ->label('')
                        ->state('Sin póliza vigente. No se podrá arrendar hasta contratar una.')
                        ->badge()
                        ->color('danger')
                        ->columnSpanFull()
                        ->visible(fn ($record) => ! $record->polizaVigente()),

                    TextEntry::make('poliza_aseguradora')
                        ->label('Aseguradora')
                        ->state(fn ($record) => $record->polizaVigente()?->aseguradora?->nombre)
                        ->visible(fn ($record) => (bool) $record->polizaVigente()),

                    TextEntry::make('poliza_numero')
                        ->label('Número de póliza')
                        ->state(fn ($record) => $record->polizaVigente()?->numero_poliza)
                        ->copyable()
                        ->visible(fn ($record) => (bool) $record->polizaVigente()),

                    TextEntry::make('poliza_cobertura')
                        ->label('Cobertura')
                        ->state(fn ($record) => self::COBERTURA[$record->polizaVigente()?->tipo_cobertura]
                            ?? $record->polizaVigente()?->tipo_cobertura)
                        ->visible(fn ($record) => (bool) $record->polizaVigente()),

                    TextEntry::make('poliza_vigencia')
                        ->label('Vigencia')
                        ->state(function ($record) {
                            $p = $record->polizaVigente();

                            if (! $p) {
                                return null;
                            }

                            $dias = (int) now()->startOfDay()->diffInDays($p->vigencia_fin, false);

                            return $p->vigencia_inicio->format('d/M/Y') . ' — ' . $p->vigencia_fin->format('d/M/Y')
                                . " ({$dias} días restantes)";
                        })
                        // Ámbar cuando quedan menos de 30 días: hay que renovar.
                        ->color(function ($record) {
                            $p = $record->polizaVigente();

                            if (! $p) {
                                return null;
                            }

                            return now()->startOfDay()->diffInDays($p->vigencia_fin, false) < 30
                                ? 'warning'
                                : null;
                        })
                        ->visible(fn ($record) => (bool) $record->polizaVigente()),
                ])
                ->columns(4),

            // ─────────────── MANTENIMIENTO ───────────────
            Section::make('Historial de uso')
                ->description('Servicios y tiempo sin rentar')
                // Secundaria: interesa al revisar, no de entrada.
                ->collapsed()
                ->schema([
                    TextEntry::make('ultimo_servicio')
                        ->label('Último servicio')
                        ->state(function ($record) {
                            $m = $record->mantenimientos()->with('tipo')->latest('fecha')->first();

                            return $m
                                ? $m->fecha->format('d/M/Y') . ' · ' . ($m->tipo?->nombre ?? 'Sin tipo')
                                : null;
                        })
                        ->placeholder('Sin servicios registrados')
                        ->columnSpan(2),

                    TextEntry::make('proximo_servicio')
                        ->label('Próximo servicio')
                        ->state(function ($record) {
                            $f = $record->mantenimientos()
                                ->whereNotNull('proximo_fecha')
                                ->max('proximo_fecha');

                            if (! $f) {
                                return null;
                            }

                            $f = \Illuminate\Support\Carbon::parse($f);

                            return $f->isPast()
                                ? $f->format('d/M/Y') . ' — vencido'
                                : $f->format('d/M/Y');
                        })
                        ->placeholder('Sin programar')
                        ->color(function ($record) {
                            $f = $record->mantenimientos()
                                ->whereNotNull('proximo_fecha')
                                ->max('proximo_fecha');

                            return $f && \Illuminate\Support\Carbon::parse($f)->isPast() ? 'danger' : null;
                        }),

                    /**
                     * Dos cifras distintas, no una sola.
                     *
                     * Lo que paga el CLIENTE es informativo: dice cuánto se ha
                     * invertido en cuidar el auto, y un número alto es buena
                     * señal para el valor de reventa. Lo que paga la
                     * ARRENDADORA sí es gasto propio y debería ser mínimo.
                     * Mezclarlas era lo que hacía ver el rendimiento peor de
                     * lo que es.
                     */
                    /**
                     * Lo que el auto estuvo parado. Con el cliente
                     * absorbiendo los costos, la ociosidad es la principal
                     * fuga de rendimiento.
                     */
                    TextEntry::make('dias_sin_rentar')
                        ->label('Días acumulados sin rentar')
                        ->state(fn ($record) => (int) $record->historialEstatus()
                            ->where('estatus_nuevo', 'disponible')
                            ->whereNotNull('fin')
                            ->sum('dias'))
                        ->formatStateUsing(fn ($state) => number_format((int) $state) . ' días')
                        ->color(fn ($state) => $state >= 60 ? 'danger' : ($state >= 30 ? 'warning' : 'gray')),

                    TextEntry::make('mantenimiento_cliente')
                        ->label('Invertido por el cliente')
                        ->state(fn ($record) => (float) Mantenimiento::query()
                            ->where('unidad_id', $record->id)
                            ->where('responsable_costo', 'arrendatario')
                            ->get()
                            ->sum(fn ($m) => $m->costo_total))
                        ->money('MXN')
                        ->color('gray')
                        ->helperText('Informativo: no es gasto de la arrendadora'),

                    TextEntry::make('mantenimiento_arrendadora')
                        ->label('A cargo de la arrendadora')
                        ->state(fn ($record) => (float) Mantenimiento::query()
                            ->where('unidad_id', $record->id)
                            ->where('responsable_costo', 'arrendadora')
                            ->get()
                            ->sum(fn ($m) => $m->costo_total))
                        ->money('MXN')
                        ->weight('bold')
                        ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                        ->helperText('Este sí resta al rendimiento'),

                    /**
                     * Dinero de la arrendadora en la calle: lo adelantó y
                     * todavía no se lo cobra al cliente.
                     */
                    TextEntry::make('pendiente_recobro')
                        ->label('Pendiente de recobro')
                        ->state(fn ($record) => (float) Mantenimiento::query()
                            ->where('unidad_id', $record->id)
                            ->where('se_recobra', true)
                            ->get()
                            ->filter(fn ($m) => $m->pendienteDeRecobro())
                            ->sum(fn ($m) => $m->costo_total))
                        ->money('MXN')
                        ->color('danger')
                        ->helperText('Adelantado por la arrendadora, sin cobrar al cliente')
                        ->visible(fn ($record) => Mantenimiento::where('unidad_id', $record->id)
                            ->where('se_recobra', true)
                            ->exists()),
                ])
                ->columns(4),

            // ─────────────── ADQUISICIÓN ───────────────
            Section::make('Adquisición')
                ->collapsed()
                ->schema([
                    TextEntry::make('proveedor.nombre')
                        ->label('Proveedor')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('fecha_adquisicion')
                        ->label('Fecha de compra')
                        ->date('d/M/Y')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('factura_folio')
                        ->label('Folio de factura')
                        ->copyable()
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('forma_adquisicion')
                        ->label('Forma de adquisición')
                        ->hidden(fn ($state) => blank($state))
                        ->formatStateUsing(fn ($state) => match ($state) {
                            'contado'            => 'Contado',
                            'credito'            => 'Crédito',
                            'arrendamiento_puro' => 'Arrendamiento puro',
                            default              => $state,
                        }),
                ])
                ->columns(4),

            // ─────────────── PIE ───────────────
            Section::make()
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Dado de alta el')
                        ->date('d/M/Y')
                        ->size('xs')
                        ->color('gray'),

                    TextEntry::make('registradoPor.name')
                        ->label('Registrado por')
                        ->size('xs')
                        ->color('gray')
                        ->placeholder('—'),
                ])
                ->columns(2),
        ]);
    }
}
