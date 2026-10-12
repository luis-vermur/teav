<?php

namespace App\Filament\Resources\Colors\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Ficha del color.
 *
 * El color importa en la reventa: es el dato que explica por qué dos autos
 * idénticos se venden a precios distintos. Por eso la ficha enseña qué
 * unidades lo traen y cuánto capital representan.
 */
class ColorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make()
                ->schema([
                    TextEntry::make('muestra')
                        ->label('Muestra')
                        ->state('')
                        ->formatStateUsing(fn ($record) => new HtmlString(
                            '<span style="display:inline-block;width:56px;height:56px;border-radius:10px;'
                            . 'border:1px solid rgba(0,0,0,.15);background:'
                            . e($record->hex ?: 'transparent') . '"></span>'
                        )),

                    TextEntry::make('hex')
                        ->label('Código')
                        ->copyable()
                        ->copyMessage('Código copiado')
                        ->placeholder('Sin código de color'),

                    TextEntry::make('unidades_count')
                        ->label('Unidades')
                        ->state(fn ($record) => $record->unidades()->count())
                        ->badge()
                        ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                    TextEntry::make('inversion')
                        ->label('Capital en este color')
                        ->state(fn ($record) => (float) $record->unidades()->sum('precio_adquisicion'))
                        ->money('MXN')
                        ->weight('bold'),
                ])
                ->columns(4),

            Section::make('Unidades de este color')
                ->schema([
                    TextEntry::make('listado_unidades')
                        ->label('')
                        ->state(fn ($record) => $record->unidades()
                            ->with('submarca.marca')
                            ->orderBy('codigo_interno')
                            ->limit(30)
                            ->get()
                            ->map(fn ($u) => $u->codigo_interno
                                . ' · ' . ($u->submarca?->nombre_completo ?? '')
                                . ' ' . $u->anio)
                            ->all())
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->placeholder('Ninguna unidad de la flota tiene este color.')
                        ->columnSpanFull(),
                ])
                ->columns(1),

            Section::make()
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Dado de alta el')
                        ->date('d/M/Y')
                        ->size('xs')
                        ->color('gray'),
                ]),
        ]);
    }
}
