<?php

namespace App\Filament\Resources\Proveedors\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ficha del proveedor.
 *
 * Un mismo proveedor puede ser agencia, taller y gestoría a la vez, por eso
 * los tipos van como badges y no como un campo suelto. Lo que interesa de
 * un taller es cuánto trabajo se le ha dado y cómo contactarlo.
 */
class ProveedorInfolist
{
    private const TIPOS = [
        'agencia'      => ['Agencia', 'info'],
        'taller'       => ['Taller', 'warning'],
        'llantera'     => ['Llantera', 'gray'],
        'gestoria'     => ['Gestoría', 'purple'],
        'verificentro' => ['Verificentro', 'gray'],
        'aseguradora'  => ['Aseguradora', 'success'],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // ─────────────── RESUMEN ───────────────
            Section::make()
                ->schema([
                    TextEntry::make('tipos')
                        ->label('Qué maneja')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::TIPOS[$state][0] ?? ucfirst((string) $state))
                        ->color(fn ($state) => self::TIPOS[$state][1] ?? 'gray')
                        ->placeholder('Sin clasificar'),

                    TextEntry::make('servicios_realizados')
                        ->label('Servicios realizados')
                        ->state(fn ($record) => $record->mantenimientos()->count())
                        ->badge()
                        ->color(fn ($state) => $state > 0 ? 'info' : 'gray'),

                    TextEntry::make('monto_acumulado')
                        ->label('Monto acumulado')
                        ->state(fn ($record) => (float) $record->mantenimientos()
                            ->get()
                            ->sum(fn ($m) => $m->costo_total))
                        ->money('MXN')
                        ->weight('bold'),

                    TextEntry::make('activo')
                        ->label('Situación')
                        ->badge()
                        ->state(fn ($record) => $record->activo ? 'Activo' : 'Inactivo')
                        ->color(fn ($record) => $record->activo ? 'success' : 'gray'),
                ])
                ->columns(4),

            // ─────────────── CONTACTO ───────────────
            Section::make('Contacto')
                ->schema([
                    TextEntry::make('rfc')
                        ->label('RFC')
                        ->copyable()
                        ->hidden(fn ($state) => blank($state)),

                    /**
                     * Un taller sin teléfono es un taller al que no puedes
                     * llamar cuando el auto se descompone: la ausencia se
                     * muestra en ámbar en lugar de ocultarse.
                     */
                    TextEntry::make('telefono')
                        ->label('Teléfono')
                        ->placeholder('Sin teléfono registrado')
                        ->color(fn ($state) => blank($state) ? 'warning' : null),

                    TextEntry::make('email')
                        ->label('Correo electrónico')
                        ->copyable()
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('contacto_nombre')
                        ->label('Persona de contacto')
                        ->placeholder('Sin contacto registrado')
                        ->color(fn ($state) => blank($state) ? 'warning' : null),

                    TextEntry::make('direccion')
                        ->label('Dirección')
                        ->state(fn ($record) => $record->direcciones()->first()?->completa)
                        ->hidden(fn ($state) => blank($state))
                        ->columnSpanFull(),
                ])
                ->columns(4),

            // ─────────────── HISTORIAL ───────────────
            Section::make('Historial de servicios')
                ->description('Los últimos trabajos que ha hecho')
                ->collapsed()
                ->visible(fn ($record) => $record->mantenimientos()->exists())
                ->schema([
                    TextEntry::make('ultimos_servicios')
                        ->label('')
                        ->state(fn ($record) => $record->mantenimientos()
                            ->with('tipo', 'unidad')
                            ->latest('fecha')
                            ->limit(10)
                            ->get()
                            ->map(fn ($m) => $m->fecha->format('d/M/Y')
                                . ' · ' . ($m->tipo?->nombre ?? 'Servicio')
                                . ' · ' . ($m->unidad?->codigo_interno ?? '')
                                . ' · $' . number_format($m->costo_total, 2))
                            ->all())
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->placeholder('Sin servicios registrados')
                        ->columnSpanFull(),
                ])
                ->columns(1),

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
                        ->label('Dado de alta el')
                        ->date('d/M/Y')
                        ->size('xs')
                        ->color('gray'),
                ])
                ->columns(2),
        ]);
    }
}
