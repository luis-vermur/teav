<?php

namespace App\Filament\Resources\Aseguradoras\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ficha de la aseguradora.
 *
 * Contesta dos preguntas distintas: a quién llamo ahora mismo (contacto) y
 * cuánto negocio le estoy dando (pólizas y prima acumulada).
 */
class AseguradoraInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make()
                ->schema([
                    TextEntry::make('polizas_vigentes')
                        ->label('Pólizas vigentes')
                        ->state(fn ($record) => $record->polizas()->where('estatus', 'vigente')->count())
                        ->badge()
                        ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                    TextEntry::make('polizas_total')
                        ->label('Pólizas históricas')
                        ->state(fn ($record) => $record->polizas()->count())
                        ->badge()
                        ->color('gray'),

                    TextEntry::make('prima_vigente')
                        ->label('Prima anual vigente')
                        ->state(fn ($record) => (float) $record->polizas()
                            ->where('estatus', 'vigente')
                            ->sum('prima_total'))
                        ->money('MXN')
                        ->weight('bold'),

                    TextEntry::make('activo')
                        ->label('Situación')
                        ->badge()
                        ->state(fn ($record) => $record->activo ? 'Activa' : 'Inactiva')
                        ->color(fn ($record) => $record->activo ? 'success' : 'gray'),
                ])
                ->columns(4),

            Section::make('A quién llamar')
                ->schema([
                    /**
                     * Sin este teléfono la aseguradora es inútil en el único
                     * momento en que hace falta: se marca en ámbar si falta.
                     */
                    TextEntry::make('telefono_siniestros')
                        ->label('Siniestros 24 h')
                        ->icon('heroicon-m-phone')
                        ->copyable()
                        ->copyMessage('Teléfono copiado')
                        ->weight('bold')
                        ->placeholder('Sin teléfono registrado')
                        ->color(fn ($state) => blank($state) ? 'warning' : null),

                    TextEntry::make('contacto_nombre')
                        ->label('Ejecutivo de cuenta')
                        ->placeholder('Sin contacto registrado'),

                    TextEntry::make('contacto_email')
                        ->label('Correo')
                        ->copyable()
                        ->placeholder('Sin correo'),

                    TextEntry::make('rfc')
                        ->label('RFC')
                        ->copyable()
                        ->placeholder('Sin RFC'),
                ])
                ->columns(4),

            Section::make('Pólizas vigentes')
                ->description('Qué unidades tiene aseguradas hoy')
                ->schema([
                    TextEntry::make('listado_polizas')
                        ->label('')
                        ->state(fn ($record) => $record->polizas()
                            ->with('unidad')
                            ->where('estatus', 'vigente')
                            ->orderBy('vigencia_fin')
                            ->limit(30)
                            ->get()
                            ->map(fn ($p) => $p->numero_poliza
                                . ' · ' . ($p->unidad?->codigo_interno ?? 'sin unidad')
                                . ' · vence ' . $p->vigencia_fin?->format('d/M/Y')
                                . ' · $' . number_format((float) $p->prima_total, 2))
                            ->all())
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->placeholder('No tiene pólizas vigentes.')
                        ->columnSpanFull(),
                ])
                ->columns(1),

            Section::make()
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Dada de alta el')
                        ->date('d/M/Y')
                        ->size('xs')
                        ->color('gray'),
                ]),
        ]);
    }
}
