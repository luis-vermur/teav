<?php

namespace App\Filament\Resources\Cotizacions\Schemas;

use App\Filament\Resources\Arrendatarios\ArrendatarioResource;
use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Resources\Unidads\UnidadResource;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ficha de la cotización.
 *
 * Una cotización puede ser de un auto de la flota o de uno que aún no se
 * compra, y puede ir dirigida a un cliente registrado o a un prospecto.
 * La ficha cambia según el caso en lugar de mostrar los dos con guiones.
 */
class CotizacionInfolist
{
    /** Situación de la cotización: etiqueta legible y color semántico. */
    private const ESTATUS = [
        'borrador'   => ['Borrador', 'gray'],
        'enviada'    => ['Enviada', 'info'],
        'aceptada'   => ['Aceptada', 'success'],
        'rechazada'  => ['Rechazada', 'danger'],
        'vencida'    => ['Vencida', 'warning'],
        'convertida' => ['Convertida en contrato', 'success'],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // ─────────────── RESUMEN ───────────────
            Section::make()
                ->schema([
                    TextEntry::make('proposito')
                        ->label('Tipo')
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn ($state) => $state === 'comercial'
                            ? 'Propuesta a cliente'
                            : 'Análisis interno'),

                    TextEntry::make('estatus')
                        ->label('Situación')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::ESTATUS[$state][0] ?? ucfirst((string) $state))
                        ->color(fn ($state) => self::ESTATUS[$state][1] ?? 'gray'),

                    // La cifra que decide si el negocio se hace o no.
                    TextEntry::make('rendimiento_anual')
                        ->label('Rendimiento anual')
                        ->formatStateUsing(fn ($state) => number_format((float) $state * 100, 2) . '%')
                        ->weight('bold')
                        ->size('lg')
                        ->color(fn ($state) => match (true) {
                            (float) $state >= 0.25 => 'success',
                            (float) $state >= 0.15 => 'warning',
                            default                => 'danger',
                        }),

                    // Una cotización vencida no sirve: la ausencia importa.
                    TextEntry::make('vigencia_hasta')
                        ->label('Vigencia')
                        ->state(function ($record) {
                            if (blank($record->vigencia_hasta)) {
                                return 'Sin vigencia definida';
                            }

                            $dias = (int) now()->startOfDay()->diffInDays($record->vigencia_hasta, false);

                            return $dias < 0
                                ? 'Venció hace ' . abs($dias) . ' días'
                                : $record->vigencia_hasta->format('d/M/Y') . " ({$dias} días)";
                        })
                        ->color(fn ($record) => blank($record->vigencia_hasta)
                            || $record->vigencia_hasta->isPast() ? 'danger' : null),
                ])
                ->columns(4),

            // ─────────────── VEHÍCULO ───────────────
            Section::make('Vehículo cotizado')
                ->schema([
                    TextEntry::make('vehiculo')
                        ->label('Vehículo')
                        ->weight('bold')
                        ->columnSpan(2)
                        // Si es una unidad de la flota, se puede navegar a ella.
                        ->url(fn ($record) => $record->unidad
                            ? UnidadResource::getUrl('view', ['record' => $record->unidad])
                            : null)
                        ->color(fn ($record) => $record->unidad ? 'primary' : null)
                        ->helperText(fn ($record) => $record->unidad
                            ? 'Unidad de la flota · ' . $record->unidad->codigo_interno
                            : 'Auto que aún no se compra'),

                    TextEntry::make('color_descripcion')
                        ->label('Color')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('precio_lista')
                        ->label('Precio de lista')
                        ->money('MXN')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('precio_adquisicion')
                        ->label('Precio de compra')
                        ->money('MXN')
                        ->weight('bold'),

                    TextEntry::make('gastos_adquisicion')
                        ->label('Gastos adicionales')
                        ->money('MXN')
                        ->hidden(fn ($state) => blank($state) || (float) $state <= 0),
                ])
                ->columns(4),

            // ─────────────── CLIENTE ───────────────
            Section::make('Cliente')
                ->visible(fn ($record) => $record->proposito === 'comercial')
                ->schema([
                    TextEntry::make('arrendatario.nombre_completo')
                        ->label('Cliente registrado')
                        ->weight('bold')
                        ->url(fn ($record) => $record->arrendatario
                            ? ArrendatarioResource::getUrl('view', ['record' => $record->arrendatario])
                            : null)
                        ->color('primary')
                        ->visible(fn ($record) => (bool) $record->arrendatario),

                    TextEntry::make('prospecto_nombre')
                        ->label('Prospecto')
                        ->weight('bold')
                        ->visible(fn ($record) => ! $record->arrendatario)
                        ->placeholder('Sin nombre'),

                    TextEntry::make('prospecto_empresa')
                        ->label('Empresa')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('prospecto_email')
                        ->label('Correo electrónico')
                        ->copyable()
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('prospecto_telefono')
                        ->label('Teléfono')
                        ->hidden(fn ($state) => blank($state)),
                ])
                ->columns(4),

            // ─────────────── PROPUESTA ───────────────
            Section::make('Propuesta')
                ->description('Lo que se le ofrece al cliente')
                ->schema([
                    TextEntry::make('renta_con_iva')
                        ->label('Renta mensual con IVA')
                        ->money('MXN')
                        ->weight('bold')
                        ->size('lg'),

                    TextEntry::make('renta_sin_iva')
                        ->label('Renta sin IVA')
                        ->money('MXN'),

                    TextEntry::make('plazo_meses')
                        ->label('Plazo')
                        ->formatStateUsing(fn ($state) => $state . ' meses'),

                    TextEntry::make('deposito_garantia')
                        ->label('Depósito en garantía')
                        ->money('MXN')
                        ->hidden(fn ($state) => blank($state) || (float) $state <= 0),

                    TextEntry::make('enganche')
                        ->label('Enganche')
                        ->money('MXN')
                        ->hidden(fn ($state) => blank($state) || (float) $state <= 0),

                    TextEntry::make('valor_residual_estimado')
                        ->label('Venta estimada al final')
                        ->money('MXN')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('limite_km_anual')
                        ->label('Límite de kilómetros')
                        ->formatStateUsing(fn ($state) => number_format((int) $state) . ' km al año')
                        ->hidden(fn ($state) => blank($state)),

                    IconEntry::make('con_opcion_compra')
                        ->label('Con opción de compra')
                        ->boolean(),
                ])
                ->columns(4),

            // ─────────────── NÚMEROS ───────────────
            Section::make('Números del negocio')
                ->description('Lo que la arrendadora gana con esta operación')
                ->schema([
                    TextEntry::make('inversion_total')
                        ->label('Inversión total')
                        ->money('MXN'),

                    TextEntry::make('ingreso_bruto_total')
                        ->label('Ingreso bruto')
                        ->money('MXN'),

                    TextEntry::make('egresos_estimados')
                        ->label('Egresos estimados')
                        ->money('MXN')
                        ->color(fn ($state) => (float) $state > 0 ? 'danger' : 'gray'),

                    TextEntry::make('ganancia_neta')
                        ->label('Ganancia neta')
                        ->money('MXN')
                        ->weight('bold')
                        ->color(fn ($state) => (float) $state > 0 ? 'success' : 'danger'),

                    TextEntry::make('rendimiento_renta')
                        ->label('Rendimiento sólo por rentas')
                        ->formatStateUsing(fn ($state) => number_format((float) $state * 100, 2) . '%'),

                    TextEntry::make('rendimiento_total')
                        ->label('Rendimiento total')
                        ->formatStateUsing(fn ($state) => number_format((float) $state * 100, 2) . '%'),

                    TextEntry::make('rendimiento_neto')
                        ->label('Rendimiento neto')
                        ->formatStateUsing(fn ($state) => number_format((float) $state * 100, 2) . '%')
                        ->helperText('Ya descontando costos'),

                    TextEntry::make('punto_equilibrio_meses')
                        ->label('Punto de equilibrio')
                        ->formatStateUsing(fn ($state) => number_format((float) $state, 1) . ' meses')
                        ->hidden(fn ($state) => blank($state)),
                ])
                ->columns(4),

            // ─────────────── SEGUIMIENTO ───────────────
            Section::make('Seguimiento')
                ->collapsed()
                ->schema([
                    TextEntry::make('fecha_cotizacion')
                        ->label('Fecha de la cotización')
                        ->date('d/M/Y'),

                    TextEntry::make('fecha_respuesta')
                        ->label('Fecha de respuesta')
                        ->date('d/M/Y')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('contrato.folio')
                        ->label('Contrato generado')
                        ->url(fn ($record) => $record->contrato
                            ? ContratoResource::getUrl('view', ['record' => $record->contrato])
                            : null)
                        ->color('primary')
                        ->visible(fn ($record) => (bool) $record->contrato),

                    // Si la rechazaron, el motivo es lo más importante de aquí.
                    TextEntry::make('motivo_rechazo')
                        ->label('Motivo del rechazo')
                        ->color('danger')
                        ->columnSpanFull()
                        ->visible(fn ($record) => $record->estatus === 'rechazada'),

                    TextEntry::make('condiciones')
                        ->label('Condiciones especiales')
                        ->hidden(fn ($state) => blank($state))
                        ->columnSpanFull(),

                    TextEntry::make('notas')
                        ->label('Notas')
                        ->hidden(fn ($state) => blank($state))
                        ->columnSpanFull(),
                ])
                ->columns(3),

            // ─────────────── PIE ───────────────
            Section::make()
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Dada de alta el')
                        ->date('d/M/Y')
                        ->size('xs')
                        ->color('gray'),

                    TextEntry::make('creadaPor.name')
                        ->label('Creada por')
                        ->size('xs')
                        ->color('gray')
                        ->placeholder('—'),
                ])
                ->columns(2),
        ]);
    }
}
