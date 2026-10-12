<?php

namespace App\Filament\Resources\Siniestros\Schemas;

use App\Filament\Resources\Arrendatarios\ArrendatarioResource;
use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Resources\PolizaSeguros\PolizaSeguroResource;
use App\Filament\Resources\Unidads\UnidadResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ficha del siniestro.
 *
 * Lo primero es en qué va el trámite y cuánto dinero está en juego; el
 * relato del hecho viene después. Mientras quede monto por recuperar hay
 * alguien que tiene que perseguirlo.
 */
class SiniestroInfolist
{
    private const TIPO = [
        'colision'       => ['Colisión', 'warning'],
        'robo_total'     => ['Robo total', 'danger'],
        'robo_parcial'   => ['Robo parcial', 'warning'],
        'cristales'      => ['Cristales', 'info'],
        'daños_terceros' => ['Daños a terceros', 'warning'],
        'otro'           => ['Otro', 'gray'],
    ];

    private const ESTATUS = [
        'reportado'     => ['Reportado', 'warning'],
        'en_valuacion'  => ['En valuación', 'info'],
        'en_reparacion' => ['En reparación', 'info'],
        'cerrado'       => ['Cerrado', 'success'],
        'perdida_total' => ['Pérdida total', 'danger'],
    ];

    private const RESPONSABLE = [
        'arrendadora'  => ['La arrendadora', 'warning'],
        'arrendatario' => ['El cliente', 'gray'],
        'tercero'      => ['Un tercero', 'info'],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // ─────────────── RESUMEN ───────────────
            Section::make()
                ->schema([
                    TextEntry::make('estatus')
                        ->label('Trámite')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::ESTATUS[$state][0] ?? ucfirst((string) $state))
                        ->color(fn ($state) => self::ESTATUS[$state][1] ?? 'gray'),

                    TextEntry::make('monto_estimado')
                        ->label('Daño estimado')
                        ->money('MXN')
                        ->placeholder('Sin valuar')
                        ->weight('bold'),

                    TextEntry::make('deducible_pagado')
                        ->label('Deducible pagado')
                        ->money('MXN')
                        ->placeholder('—'),

                    TextEntry::make('responsable_deducible')
                        ->label('¿Quién lo absorbió?')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::RESPONSABLE[$state][0] ?? 'Sin definir')
                        ->color(fn ($state) => self::RESPONSABLE[$state][1] ?? 'gray')
                        ->placeholder('Sin definir'),
                ])
                ->columns(4),

            // ─────────────── DEL HECHO ───────────────
            Section::make('Del hecho')
                ->schema([
                    TextEntry::make('tipo')
                        ->label('Tipo de siniestro')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::TIPO[$state][0] ?? ucfirst((string) $state))
                        ->color(fn ($state) => self::TIPO[$state][1] ?? 'gray'),

                    TextEntry::make('fecha_siniestro')
                        ->label('Fecha y hora')
                        ->dateTime('d/M/Y H:i'),

                    TextEntry::make('numero_reporte')
                        ->label('Número de reporte')
                        ->copyable()
                        ->placeholder('Sin número de reporte')
                        // Sin número de reporte no hay trámite que seguir.
                        ->color(fn ($state) => blank($state) ? 'warning' : null),

                    TextEntry::make('lugar')
                        ->label('Lugar')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('descripcion')
                        ->label('Cómo ocurrió')
                        ->hidden(fn ($state) => blank($state))
                        ->columnSpanFull(),
                ])
                ->columns(4),

            // ─────────────── TRÁMITE ───────────────
            Section::make('Trámite con la aseguradora')
                ->schema([
                    TextEntry::make('poliza.numero_poliza')
                        ->label('Póliza')
                        ->placeholder('Sin póliza asociada')
                        ->helperText(fn ($record) => $record->poliza?->aseguradora?->nombre)
                        ->url(fn ($record) => $record->poliza
                            ? PolizaSeguroResource::getUrl('view', ['record' => $record->poliza])
                            : null)
                        ->color(fn ($record) => $record->poliza ? 'primary' : 'danger'),

                    TextEntry::make('monto_recuperado')
                        ->label('Recuperado')
                        ->money('MXN')
                        ->placeholder('Nada recuperado aún')
                        ->color('success'),

                    // Mientras quede saldo, hay dinero en la calle.
                    TextEntry::make('por_recuperar')
                        ->label('Por recuperar')
                        ->state(fn ($record) => max(
                            0,
                            (float) $record->monto_estimado - (float) $record->monto_recuperado
                        ))
                        ->money('MXN')
                        ->weight('bold')
                        ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                    TextEntry::make('fecha_cierre')
                        ->label('Fecha de cierre')
                        ->date('d/M/Y')
                        ->placeholder('Trámite abierto'),
                ])
                ->columns(4),

            // ─────────────── RELACIONADOS ───────────────
            Section::make('Relacionados')
                ->schema([
                    TextEntry::make('unidad.descripcion')
                        ->label('Vehículo')
                        ->weight('bold')
                        ->helperText(fn ($record) => $record->unidad?->codigo_interno)
                        ->url(fn ($record) => $record->unidad
                            ? UnidadResource::getUrl('view', ['record' => $record->unidad])
                            : null)
                        ->color('primary'),

                    TextEntry::make('contrato.folio')
                        ->label('Contrato')
                        ->placeholder('Sin contrato')
                        ->url(fn ($record) => $record->contrato
                            ? ContratoResource::getUrl('view', ['record' => $record->contrato])
                            : null)
                        ->color(fn ($record) => $record->contrato ? 'primary' : 'gray'),

                    TextEntry::make('arrendatario.nombre_completo')
                        ->label('Cliente')
                        ->placeholder('Sin cliente')
                        ->url(fn ($record) => $record->arrendatario
                            ? ArrendatarioResource::getUrl('view', ['record' => $record->arrendatario])
                            : null)
                        ->color(fn ($record) => $record->arrendatario ? 'primary' : 'gray'),
                ])
                ->columns(3),

            Section::make('Notas')
                ->collapsed()
                ->visible(fn ($record) => filled($record->notas))
                ->schema([
                    TextEntry::make('notas')
                        ->label('')
                        ->columnSpanFull(),
                ]),

            // ─────────────── PIE ───────────────
            Section::make()
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Registrado el')
                        ->date('d/M/Y')
                        ->size('xs')
                        ->color('gray'),
                ])
                ->columns(2),
        ]);
    }
}
