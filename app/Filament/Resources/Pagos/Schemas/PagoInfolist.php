<?php

namespace App\Filament\Resources\Pagos\Schemas;

use App\Filament\Resources\Arrendatarios\ArrendatarioResource;
use App\Filament\Resources\Contratos\ContratoResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ficha del pago.
 *
 * La pregunta que resuelve esta pantalla es "¿a qué se fue este dinero?".
 * Por eso la aplicación peso por peso es la sección central y no un
 * detalle escondido.
 */
class PagoInfolist
{
    private const ESTATUS = [
        'registrado' => ['Registrado', 'warning'],
        'confirmado' => ['Confirmado', 'info'],
        'aplicado'   => ['Aplicado', 'success'],
        'reversado'  => ['Reversado', 'danger'],
        'rechazado'  => ['Rechazado', 'danger'],
    ];

    private const METODO = [
        'transferencia' => 'Transferencia',
        'deposito'      => 'Depósito',
        'efectivo'      => 'Efectivo',
        'cheque'        => 'Cheque',
        'tarjeta'       => 'Tarjeta',
        'domiciliacion' => 'Domiciliación',
        'otro'          => 'Otro método',
    ];

    private const CONCEPTO = [
        'renta'              => 'Renta',
        'renta_proporcional' => 'Renta parcial',
        'seguro'             => 'Seguro',
        'gastos_iniciales'   => 'Gastos iniciales',
        'recobro'            => 'Recobro de mantenimiento',
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

                    TextEntry::make('monto')
                        ->label('Monto recibido')
                        ->money('MXN')
                        ->weight('bold')
                        ->size('lg'),

                    TextEntry::make('fecha_pago')
                        ->label('Fecha del pago')
                        ->date('d/M/Y'),

                    /**
                     * Un pago con saldo sin aplicar es dinero que entró pero
                     * no se acreditó: el cliente sigue apareciendo como
                     * deudor. Se muestra siempre, no sólo cuando hay saldo.
                     */
                    TextEntry::make('sin_aplicar')
                        ->label('Sin aplicar')
                        ->state(fn ($record) => $record->montoDisponible())
                        ->formatStateUsing(fn ($state) => $state > 0
                            ? '$' . number_format($state, 2)
                            : 'Todo aplicado')
                        ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                        ->weight('bold'),
                ])
                ->columns(4),

            // ─────────────── PARTES ───────────────
            Section::make('Quién pagó')
                ->schema([
                    TextEntry::make('arrendatario.nombre_completo')
                        ->label('Cliente')
                        ->weight('bold')
                        ->url(fn ($record) => $record->arrendatario
                            ? ArrendatarioResource::getUrl('view', ['record' => $record->arrendatario])
                            : null)
                        ->color('primary'),

                    TextEntry::make('contrato.folio')
                        ->label('Contrato')
                        ->placeholder('Sin contrato')
                        ->url(fn ($record) => $record->contrato
                            ? ContratoResource::getUrl('view', ['record' => $record->contrato])
                            : null)
                        ->color(fn ($record) => $record->contrato ? 'primary' : 'gray'),
                ])
                ->columns(2),

            // ─────────────── ORIGEN ───────────────
            Section::make('Origen del dinero')
                ->schema([
                    TextEntry::make('metodo_pago')
                        ->label('Método')
                        ->formatStateUsing(fn ($state) => self::METODO[$state] ?? ucfirst((string) $state)),

                    TextEntry::make('referencia')
                        ->label('Referencia')
                        ->copyable()
                        ->placeholder('Sin referencia')
                        // Una transferencia sin clave de rastreo es difícil de conciliar.
                        ->color(fn ($state) => blank($state) ? 'warning' : null),

                    TextEntry::make('banco_origen')
                        ->label('Banco de origen')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('cuenta_destino')
                        ->label('Cuenta destino')
                        ->hidden(fn ($state) => blank($state)),
                ])
                ->columns(4),

            // ─────────────── APLICACIÓN ───────────────
            Section::make('¿A qué se aplicó?')
                ->description('El destino de cada peso recibido')
                ->schema([
                    TextEntry::make('aplicaciones_detalle')
                        ->label('')
                        ->state(function ($record) {
                            $lineas = $record->aplicaciones()
                                ->with('pagoProgramado', 'penalizacion')
                                ->get()
                                ->map(function ($a) {
                                    $monto = '$' . number_format((float) $a->monto, 2);

                                    if ($a->penalizacion) {
                                        return "Recargo por mora — {$monto}";
                                    }

                                    if ($pp = $a->pagoProgramado) {
                                        $concepto = self::CONCEPTO[$pp->concepto] ?? ucfirst((string) $pp->concepto);
                                        $detalle  = $pp->descripcion
                                            ?: "Exhibición {$pp->numero_exhibicion}";

                                        return "{$concepto} · {$detalle} — {$monto}";
                                    }

                                    return "Aplicación sin destino — {$monto}";
                                })
                                ->all();

                            return $lineas ?: null;
                        })
                        ->listWithLineBreaks()
                        ->bulleted()
                        // Un pago sin aplicar es justo lo que hay que ver.
                        ->placeholder('Todavía no se ha aplicado a ninguna exhibición')
                        ->columnSpanFull(),

                    TextEntry::make('monto_aplicado')
                        ->label('Total aplicado')
                        ->money('MXN')
                        ->weight('bold')
                        ->color('success'),

                    TextEntry::make('saldo_a_favor')
                        ->label('Saldo a favor')
                        ->money('MXN')
                        ->color(fn ($state) => (float) $state > 0 ? 'info' : 'gray')
                        ->helperText(fn ($state) => (float) $state > 0
                            ? 'Se abonará al siguiente periodo'
                            : null),
                ])
                ->columns(2),

            // ─────────────── REGISTRO ───────────────
            Section::make('Registro')
                ->collapsed()
                ->schema([
                    TextEntry::make('registradoPor.name')
                        ->label('Capturado por')
                        ->placeholder('—'),

                    TextEntry::make('confirmadoPor.name')
                        ->label('Confirmado por')
                        ->placeholder('Sin confirmar'),

                    TextEntry::make('created_at')
                        ->label('Capturado el')
                        ->date('d/M/Y'),

                    // Si se reversó, el motivo es lo más importante del registro.
                    TextEntry::make('motivo_reverso')
                        ->label('Motivo del reverso')
                        ->color('danger')
                        ->columnSpanFull()
                        ->visible(fn ($record) => filled($record->motivo_reverso)),

                    TextEntry::make('notas')
                        ->label('Notas')
                        ->hidden(fn ($state) => blank($state))
                        ->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }
}
