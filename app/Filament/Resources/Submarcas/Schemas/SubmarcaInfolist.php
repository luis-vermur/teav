<?php

namespace App\Filament\Resources\Submarcas\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ficha del modelo.
 *
 * La pregunta que se contesta aquí es si conviene seguir comprando este
 * modelo: cuántos hay, qué costaron y cómo se están rentando.
 */
class SubmarcaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make()
                ->schema([
                    TextEntry::make('unidades_count')
                        ->label('Unidades')
                        ->state(fn ($record) => $record->unidades()->count())
                        ->badge()
                        ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                    TextEntry::make('arrendadas')
                        ->label('Arrendadas')
                        ->state(fn ($record) => $record->unidades()->where('estatus', 'arrendada')->count())
                        ->badge()
                        ->color(fn ($state) => $state > 0 ? 'info' : 'gray'),

                    TextEntry::make('inversion')
                        ->label('Capital invertido')
                        ->state(fn ($record) => (float) $record->unidades()->sum('precio_adquisicion'))
                        ->money('MXN')
                        ->weight('bold'),

                    TextEntry::make('precio_promedio')
                        ->label('Precio promedio')
                        ->state(fn ($record) => (float) $record->unidades()->avg('precio_adquisicion'))
                        ->money('MXN')
                        ->placeholder('Sin unidades'),
                ])
                ->columns(4),

            Section::make('Datos')
                ->schema([
                    TextEntry::make('marca.nombre')
                        ->label('Marca'),

                    TextEntry::make('segmento')
                        ->label('Segmento')
                        ->badge()
                        ->color('gray')
                        ->placeholder('Sin clasificar'),

                    TextEntry::make('activo')
                        ->label('Situación')
                        ->badge()
                        ->state(fn ($record) => $record->activo ? 'Activo' : 'Inactivo')
                        ->color(fn ($record) => $record->activo ? 'success' : 'gray'),

                    TextEntry::make('created_at')
                        ->label('Dado de alta el')
                        ->date('d/M/Y')
                        ->color('gray'),
                ])
                ->columns(4),

            Section::make('Unidades de este modelo')
                ->schema([
                    TextEntry::make('listado_unidades')
                        ->label('')
                        ->state(fn ($record) => $record->unidades()
                            ->orderBy('codigo_interno')
                            ->limit(30)
                            ->get()
                            ->map(fn ($u) => $u->codigo_interno
                                . ' · ' . $u->anio
                                . ' · ' . ucfirst(str_replace('_', ' ', (string) $u->estatus)))
                            ->all())
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->placeholder('Todavía no hay unidades de este modelo.')
                        ->columnSpanFull(),
                ])
                ->columns(1),
        ]);
    }
}
