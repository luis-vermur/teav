<?php

namespace App\Filament\Resources\Contratos\Schemas;

use App\Filament\Resources\Arrendatarios\ArrendatarioResource;
use App\Filament\Resources\Unidads\UnidadResource;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ficha del contrato.
 *
 * Los cálculos salen de los métodos del modelo (saldoVencido, avance,
 * saldoDeposito, renta_total_contrato) en lugar de repetirse aquí. Las
 * condiciones de penalización se traducen a lenguaje natural: nadie
 * debería tener que interpretar "porcentaje_mensual = 0.0500".
 */
class ContratoInfolist
{
    /** Situación del contrato: etiqueta legible y color semántico. */
    private const ESTATUS = [
        'borrador'          => ['Borrador', 'gray'],
        'por_firmar'        => ['Por firmar', 'warning'],
        'vigente'           => ['Vigente', 'success'],
        'en_incumplimiento' => ['En incumplimiento', 'danger'],
        'por_vencer'        => ['Por vencer', 'warning'],
        'finalizado'        => ['Finalizado', 'gray'],
        'rescindido'        => ['Rescindido', 'danger'],
        'cancelado'         => ['Cancelado', 'gray'],
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

                    TextEntry::make('avance_pago')
                        ->label('Avance de pago')
                        ->state(fn ($record) => $record->exhibicionesPagadas() . ' de ' . $record->plazo_meses)
                        ->helperText(fn ($record) => $record->avance() . '% cubierto'),

                    // La ausencia de adeudo también informa: no se oculta.
                    TextEntry::make('saldo_vencido')
                        ->label('Saldo vencido')
                        ->state(fn ($record) => $record->saldoVencido())
                        ->formatStateUsing(fn ($state) => $state > 0
                            ? '$' . number_format($state, 2)
                            : 'Al corriente')
                        ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                        ->weight('bold'),

                    /**
                     * Un contrato en borrador no tiene calendario todavía, y
                     * eso confunde: parece que el sistema falló. Aquí se dice
                     * explícitamente que falta activarlo y qué lo impide.
                     */
                    TextEntry::make('calendario')
                        ->label('Calendario de pagos')
                        ->state(function ($record) {
                            $cargos = $record->pagosProgramados()->count();

                            if ($cargos > 0) {
                                return $cargos . ' cargos generados';
                            }

                            return $record->puedeActivarse()
                                ? 'Sin generar — activa el contrato'
                                : 'Sin generar';
                        })
                        ->badge()
                        ->color(fn ($record) => $record->pagosProgramados()->exists() ? 'success' : 'warning')
                        ->helperText(fn ($record) => $record->pagosProgramados()->exists()
                            ? null
                            : ($record->puedeActivarse()
                                ? 'El calendario se crea al activar el contrato'
                                : 'Falta: ' . implode(' · ', $record->motivosParaNoActivar())))
                        ->columnSpanFull(),

                    TextEntry::make('dias_para_vencer')
                        ->label('Días para vencer')
                        ->state(fn ($record) => $record->diasParaVencer())
                        ->formatStateUsing(fn ($state) => $state < 0
                            ? abs($state) . ' días vencido'
                            : $state . ' días')
                        ->color(fn ($state) => match (true) {
                            $state < 0  => 'danger',
                            $state < 30 => 'warning',
                            default     => null,
                        }),
                ])
                ->columns(4),

            // ─────────────── PARTES ───────────────
            Section::make('Partes')
                ->schema([
                    TextEntry::make('arrendatario.nombre_completo')
                        ->label('Cliente')
                        ->weight('bold')
                        ->url(fn ($record) => $record->arrendatario
                            ? ArrendatarioResource::getUrl('view', ['record' => $record->arrendatario])
                            : null)
                        ->color('primary'),

                    TextEntry::make('unidad.descripcion')
                        ->label('Vehículo')
                        ->weight('bold')
                        ->helperText(fn ($record) => $record->unidad?->codigo_interno)
                        ->url(fn ($record) => $record->unidad
                            ? UnidadResource::getUrl('view', ['record' => $record->unidad])
                            : null)
                        ->color('primary'),
                ])
                ->columns(2),

            // ─────────────── TÉRMINOS ───────────────
            Section::make('Términos')
                ->schema([
                    TextEntry::make('renta_sin_iva')
                        ->label('Renta sin IVA')
                        ->money('MXN'),

                    TextEntry::make('iva')
                        ->label('IVA')
                        ->state(fn ($record) => (float) $record->renta_con_iva - (float) $record->renta_sin_iva)
                        ->money('MXN')
                        ->helperText(fn ($record) => number_format((float) $record->tasa_iva * 100, 0) . '%'),

                    TextEntry::make('renta_con_iva')
                        ->label('Renta mensual')
                        ->money('MXN')
                        ->weight('bold')
                        ->size('lg'),

                    TextEntry::make('plazo_meses')
                        ->label('Plazo')
                        ->formatStateUsing(fn ($state) => $state . ' meses'),

                    TextEntry::make('fecha_inicio')
                        ->label('Fecha de inicio')
                        ->date('d/M/Y'),

                    TextEntry::make('fecha_fin')
                        ->label('Fecha de fin')
                        ->date('d/M/Y'),

                    TextEntry::make('dia_corte')
                        ->label('Día de corte')
                        ->formatStateUsing(fn ($state) => blank($state) ? null : 'Día ' . $state)
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('periodicidad')
                        ->label('Periodicidad')
                        ->formatStateUsing(fn ($state) => ucfirst((string) $state))
                        ->hidden(fn ($state) => blank($state)),
                ])
                ->columns(4),

            // ─────────────── ESTADO DE CUENTA ───────────────
            Section::make('Estado de cuenta')
                ->schema([
                    TextEntry::make('renta_total_contrato')
                        ->label('Total del contrato')
                        ->money('MXN')
                        ->weight('bold'),

                    TextEntry::make('ya_cobrado')
                        ->label('Ya cobrado')
                        ->state(fn ($record) => (float) $record->pagosProgramados()->sum('monto_aplicado'))
                        ->money('MXN')
                        ->color('success'),

                    TextEntry::make('falta_por_cobrar')
                        ->label('Falta por cobrar')
                        ->state(fn ($record) => (float) $record->pagosProgramados()->sum('saldo'))
                        ->money('MXN'),

                    TextEntry::make('saldo_deposito')
                        ->label('Depósito en garantía')
                        ->state(fn ($record) => $record->saldoDeposito())
                        ->money('MXN'),

                    TextEntry::make('recargos_pendientes')
                        ->label('Recargos pendientes')
                        ->state(fn ($record) => (float) $record->penalizaciones()
                            ->whereIn('estatus', ['pendiente', 'parcial'])
                            ->sum('saldo'))
                        ->money('MXN')
                        ->color(fn ($state) => $state > 0 ? 'danger' : null),
                ])
                ->columns(5),

            // ─────────────── CONDICIONES ───────────────
            Section::make('Condiciones')
                ->description('Tolerancia, recargos y límites pactados')
                // Secundaria: no compite con el estado de cuenta.
                ->collapsed()
                ->schema([
                    TextEntry::make('dias_gracia')
                        ->label('Días de tolerancia')
                        ->formatStateUsing(fn ($state) => (int) $state === 0
                            ? 'Sin tolerancia'
                            : $state . ' días'),

                    // El recargo, explicado como lo diría una persona.
                    TextEntry::make('penalizacion')
                        ->label('Recargo por atraso')
                        ->state(fn ($record) => self::explicarPenalizacion($record))
                        ->columnSpan(3),

                    TextEntry::make('limite_km_anual')
                        ->label('Límite de kilómetros')
                        ->formatStateUsing(fn ($state) => number_format((int) $state) . ' km al año')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('costo_km_excedente')
                        ->label('Costo por km excedente')
                        ->money('MXN')
                        ->hidden(fn ($state) => blank($state)),

                    IconEntry::make('con_opcion_compra')
                        ->label('Opción de compra')
                        ->boolean(),

                    TextEntry::make('valor_residual_pactado')
                        ->label('Precio de la opción de compra')
                        ->money('MXN')
                        // Sólo tiene sentido si la opción existe.
                        ->visible(fn ($record) => (bool) $record->con_opcion_compra)
                        ->hidden(fn ($state) => blank($state)),
                ])
                ->columns(4),

            // ─────────────── CIERRE ───────────────
            // Sólo existe cuando el contrato ya terminó.
            Section::make('Cierre')
                ->visible(fn ($record) => (bool) $record->cierre)
                ->schema([
                    TextEntry::make('cierre.tipo_cierre')
                        ->label('Tipo de cierre')
                        ->badge()
                        ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', (string) $state))),

                    TextEntry::make('cierre.fecha_cierre')
                        ->label('Fecha de cierre')
                        ->date('d/M/Y'),

                    TextEntry::make('cierre.kilometraje_final')
                        ->label('Kilometraje final')
                        ->formatStateUsing(fn ($state) => number_format((int) $state) . ' km')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('cierre.km_excedente')
                        ->label('Km excedente')
                        ->formatStateUsing(fn ($state) => number_format((int) $state) . ' km')
                        ->color(fn ($state) => $state > 0 ? 'warning' : null)
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('cierre.cargo_km_excedente')
                        ->label('Cargo por km excedente')
                        ->money('MXN')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('cierre.deposito_retenido')
                        ->label('Depósito retenido')
                        ->money('MXN')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('cierre.deposito_devuelto')
                        ->label('Depósito devuelto')
                        ->money('MXN')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('cierre.observaciones')
                        ->label('Observaciones')
                        ->hidden(fn ($state) => blank($state))
                        ->columnSpanFull(),
                ])
                ->columns(4),

            // ─────────────── DOCUMENTOS Y NOTAS ───────────────
            Section::make('Documentos y notas')
                ->collapsed()
                ->visible(fn ($record) => filled($record->clausulas_especiales) || filled($record->notas))
                ->schema([
                    TextEntry::make('clausulas_especiales')
                        ->label('Cláusulas especiales')
                        ->hidden(fn ($state) => blank($state))
                        ->columnSpanFull(),

                    TextEntry::make('notas')
                        ->label('Notas')
                        ->hidden(fn ($state) => blank($state))
                        ->columnSpanFull(),
                ])
                ->columns(1),

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

    /**
     * Traduce tipo + valor de penalización a una frase legible.
     * Los porcentajes se guardan como fracción (0.05 = 5%).
     */
    private static function explicarPenalizacion($record): string
    {
        $valor  = (float) $record->valor_penalizacion;
        $gracia = (int) $record->dias_gracia;
        $desde  = $gracia > 0 ? ", después de {$gracia} días" : '';

        $frase = match ($record->tipo_penalizacion) {
            'porcentaje_mensual' => number_format($valor * 100, 2) . '% mensual sobre lo vencido' . $desde,
            'porcentaje_unico'   => number_format($valor * 100, 2) . '% único sobre la exhibición vencida' . $desde,
            'monto_fijo_diario'  => '$' . number_format($valor, 2) . ' por cada día de atraso' . $desde,
            'ninguna'            => 'Sin recargo por atraso',
            default              => 'Sin especificar',
        };

        if ($record->tipo_penalizacion !== 'ninguna' && filled($record->tope_penalizacion)) {
            $frase .= ' (tope: $' . number_format((float) $record->tope_penalizacion, 2) . ')';
        }

        return $frase;
    }
}
