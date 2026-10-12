<?php

namespace App\Filament\Resources\Marcas\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ficha de la marca.
 *
 * Una marca por sí sola no dice nada: lo que interesa es cuánta flota
 * cuelga de ella y cuánto capital representa.
 */
class MarcaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make()
                ->schema([
                    TextEntry::make('submarcas_count')
                        ->label('Modelos')
                        ->state(fn ($record) => $record->submarcas()->count())
                        ->badge()
                        ->color(fn ($state) => $state > 0 ? 'info' : 'gray'),

                    TextEntry::make('unidades_count')
                        ->label('Unidades en la flota')
                        ->state(fn ($record) => $record->unidades()->count())
                        ->badge()
                        ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                    TextEntry::make('inversion')
                        ->label('Capital invertido')
                        ->state(fn ($record) => (float) $record->unidades()->sum('precio_adquisicion'))
                        ->money('MXN')
                        ->weight('bold'),

                    TextEntry::make('activo')
                        ->label('Situación')
                        ->badge()
                        ->state(fn ($record) => $record->activo ? 'Activa' : 'Inactiva')
                        ->color(fn ($record) => $record->activo ? 'success' : 'gray'),
                ])
                ->columns(4),

            Section::make('Datos')
                ->schema([
                    TextEntry::make('pais_origen')
                        ->label('País de origen')
                        ->placeholder('Sin especificar'),

                    TextEntry::make('created_at')
                        ->label('Dada de alta el')
                        ->date('d/M/Y')
                        ->color('gray'),
                ])
                ->columns(2),

            Section::make('Modelos de esta marca')
                ->description('Cuántas unidades hay de cada uno')
                ->schema([
                    TextEntry::make('listado_submarcas')
                        ->label('')
                        ->state(fn ($record) => $record->submarcas()
                            ->withCount('unidades')
                            ->orderBy('nombre')
                            ->get()
                            ->map(fn ($s) => $s->nombre
                                . ($s->segmento ? " · {$s->segmento}" : '')
                                . ' · ' . ($s->unidades_count == 1
                                    ? '1 unidad'
                                    : "{$s->unidades_count} unidades"))
                            ->all())
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->placeholder('Esta marca todavía no tiene modelos dados de alta.')
                        ->columnSpanFull(),
                ])
                ->columns(1),
        ]);
    }
}
